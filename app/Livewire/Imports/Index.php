<?php

namespace App\Livewire\Imports;

use App\Models\Sequence;
use App\Services\Fattura24ContactImporter;
use App\Services\InvoiceXmlImportService;
use App\Settings\InvoiceSettings;
use App\Traits\Toast;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use Toast;
    use WithFileUploads;

    // Modal control
    public bool $showModal = false;

    public string $importType = '';

    // State shown after a successful import run
    public ?array $importResult = null;

    // XML import fields
    public $xmlFile = null;

    // Fattura24 contact import fields
    public $csvFile = null;

    public bool $updateExisting = false;

    /**
     * Open the import modal for the given import type.
     *
     * Valid types: 'xml_sales', 'xml_purchase', 'xml_self_invoice', 'fattura24_contacts'
     */
    public function openImport(string $type): void
    {
        $this->importType = $type;
        $this->importResult = null;
        $this->xmlFile = null;
        $this->csvFile = null;
        $this->updateExisting = false;
        $this->resetValidation();
        $this->showModal = true;
    }

    /**
     * Dispatch to the correct import handler based on the active import type.
     */
    public function runImport(): void
    {
        match ($this->importType) {
            'xml_sales' => $this->runXmlImport('electronic_invoice'),
            'xml_purchase' => $this->runXmlImport('purchase'),
            'xml_self_invoice' => $this->runXmlImport('self_invoice'),
            'fattura24_contacts' => $this->runFattura24ContactImport(),
            default => null,
        };
    }

    /**
     * Import Fattura Elettronica XML files (sales or purchase category).
     *
     * Accepts a single .xml/.p7m file or a .zip archive containing multiple XML files.
     * The sequence is resolved automatically from InvoiceSettings defaults,
     * falling back to the first available sequence of the right category.
     */
    protected function runXmlImport(string $category): void
    {
        $this->validate([
            'xmlFile' => 'required|file|mimes:xml,p7m,zip|max:10240',
        ]);

        $sequenceId = $this->resolveDefaultSequenceId($category);

        if ($sequenceId === null) {
            $this->error(__('app.imports.no_sequence_available'));

            return;
        }

        try {
            $service = app(InvoiceXmlImportService::class);
            $filePath = $this->xmlFile->getRealPath();
            $extension = strtolower($this->xmlFile->getClientOriginalExtension());

            if ($extension === 'zip') {
                $this->importXmlFromZip($service, $filePath, $sequenceId, $category);
            } else {
                $service->importXml(file_get_contents($filePath), $sequenceId, $category);
            }

            $resultType = match ($category) {
                'electronic_invoice' => 'xml_sales',
                'purchase' => 'xml_purchase',
                'self_invoice' => 'xml_self_invoice',
            };

            $this->importResult = [
                'type' => $resultType,
                'stats' => $service->getStats(),
                'errors' => $service->getErrors(),
            ];
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Open a ZIP archive and import every .xml/.p7m entry found inside.
     *
     * Stats are accumulated on the same service instance across all files.
     */
    protected function importXmlFromZip(InvoiceXmlImportService $service, string $zipPath, int $sequenceId, string $category): void
    {
        $zip = new \ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException(__('app.imports.zip_open_error'));
        }

        $xmlFound = false;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            $entryExtension = strtolower(pathinfo($entryName, PATHINFO_EXTENSION));

            // Skip directories and non-XML files
            if (! in_array($entryExtension, ['xml', 'p7m'])) {
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

    /**
     * Resolve the sequence ID to use for a given category.
     *
     * Uses the default sequence from InvoiceSettings if configured,
     * otherwise falls back to the first available sequence of that category.
     */
    protected function resolveDefaultSequenceId(string $category): ?int
    {
        $settings = app(InvoiceSettings::class);

        // Prefer the explicitly configured default for this category
        $defaultId = match ($category) {
            'electronic_invoice' => $settings->default_sequence_sales,
            'purchase' => $settings->default_sequence_purchase,
            'self_invoice' => $settings->default_sequence_self_invoice,
            default => null,
        };

        if ($defaultId !== null && Sequence::where('id', $defaultId)->exists()) {
            return $defaultId;
        }

        // Fallback: first sequence of the right type
        $fallback = Sequence::where('type', $category)->orderBy('id')->first();

        return $fallback?->id;
    }

    /**
     * Import contacts from a Fattura24 CSV address book export.
     */
    protected function runFattura24ContactImport(): void
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        try {
            $service = new Fattura24ContactImporter;
            $result = $service->import($this->csvFile->getRealPath(), $this->updateExisting);

            $this->importResult = [
                'type' => 'fattura24_contacts',
                'stats' => $result['stats'],
                'errors' => $result['errors'],
            ];
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * Reset the import form so the user can run another import of the same type.
     */
    public function resetImport(): void
    {
        $this->importResult = null;
        $this->xmlFile = null;
        $this->csvFile = null;
        $this->updateExisting = false;
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.imports.index');
    }
}
