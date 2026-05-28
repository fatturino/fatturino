<?php

namespace App\Http\Controllers;

use App\Models\Sequence;
use App\Services\Fattura24ContactImporter;
use App\Services\InvoiceXmlImportService;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\FiscalRegimePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(): Response
    {
        $companySettings = app(CompanySettings::class);

        return Inertia::render('Imports/Index', [
            'importResult' => session('importResult'),
            'selfInvoiceImportEnabled' => FiscalRegimePolicy::supportsSelfInvoices(
                $companySettings->company_fiscal_regime,
                $companySettings->rf19_self_invoices_enabled
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $importType = $request->input('import_type');

        return match ($importType) {
            'xml_sales' => $this->handleXmlImport($request, 'electronic_invoice'),
            'xml_purchase' => $this->handleXmlImport($request, 'purchase'),
            'xml_self_invoice' => $this->handleXmlImport($request, 'self_invoice'),
            'fattura24_contacts' => $this->handleFattura24Import($request),
            default => back()->withErrors(['import_type' => 'Tipo import non valido.']),
        };
    }

    private function handleXmlImport(Request $request, string $category): RedirectResponse
    {
        if ($category === 'self_invoice') {
            $settings = app(CompanySettings::class);
            $allowed = FiscalRegimePolicy::supportsSelfInvoices(
                $settings->company_fiscal_regime,
                $settings->rf19_self_invoices_enabled
            );
            if (! $allowed) {
                abort(403, 'Import autofatture disabilitato per il regime fiscale corrente.');
            }
        }

        $request->validate([
            'xml_file' => 'required|array|min:1',
            'xml_file.*' => 'required|file|mimes:xml,p7m,zip|max:10240',
        ]);

        $sequenceId = $this->resolveDefaultSequenceId($category);
        if ($sequenceId === null) {
            return back()->withErrors(['xml_file' => __('app.imports.no_sequence_available')]);
        }

        try {
            $service = app(InvoiceXmlImportService::class);
            $files = $request->file('xml_file', []);

            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                $extension = strtolower($file->getClientOriginalExtension());

                if ($extension === 'zip') {
                    $this->importXmlFromZip($service, $filePath, $sequenceId, $category);
                    continue;
                }

                $service->importXml(file_get_contents($filePath), $sequenceId, $category);
            }

            return redirect()->route('imports.index')->with('importResult', [
                'type' => $request->input('import_type'),
                'stats' => $service->getStats(),
                'errors' => $service->getErrors(),
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['xml_file' => $e->getMessage()]);
        }
    }

    private function handleFattura24Import(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $updateExisting = $request->boolean('update_existing');

        try {
            $importer = app(Fattura24ContactImporter::class);
            $file = $request->file('csv_file');
            $importer->import($file->getRealPath(), $updateExisting);

            return redirect()->route('imports.index')->with('importResult', [
                'type' => 'fattura24_contacts',
                'stats' => $importer->getStats(),
                'errors' => $importer->getErrors(),
            ]);
        } catch (\Exception $e) {
            return back()->withErrors(['csv_file' => $e->getMessage()]);
        }
    }

    private function importXmlFromZip(InvoiceXmlImportService $service, string $zipPath, int $sequenceId, string $category): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException(__('app.imports.zip_open_error'));
        }

        $xmlFound = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $entryExtension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));
            if (! in_array($entryExtension, ['xml', 'p7m'], true)) {
                continue;
            }
            if (str_contains($entryName, '_metaDato')) {
                continue;
            }

            $xmlContent = $zip->getFromIndex($i);
            if ($xmlContent === false) {
                continue;
            }

            $xmlFound = true;
            $service->importXml($xmlContent, $sequenceId, $category);
        }
        $zip->close();

        if (! $xmlFound) {
            throw new \RuntimeException(__('app.imports.zip_no_xml'));
        }
    }

    private function resolveDefaultSequenceId(string $category): ?int
    {
        $settings = app(InvoiceSettings::class);

        $settingKey = match ($category) {
            'electronic_invoice' => 'default_sequence_sales',
            'purchase' => 'default_sequence_purchase',
            'self_invoice' => 'default_sequence_self_invoice',
        };

        return $settings->{$settingKey}
            ?? Sequence::where('type', $category)->orderByDesc('is_system')->value('id');
    }
}
