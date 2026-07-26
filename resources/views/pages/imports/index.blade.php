<?php

use App\Models\Sequence;
use App\Services\Fattura24ContactImporter;
use App\Services\InvoiceXmlImportService;
use App\Services\PostHogTelemetryService;
use App\Settings\CompanySettings;
use App\Settings\InvoiceSettings;
use App\Support\FiscalRegimePolicy;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] class extends Component {
    use WithFileUploads;

    public string $importType = 'xml_sales';
    /** @var array<int, TemporaryUploadedFile> */
    public array $xmlFiles = [];
    public ?TemporaryUploadedFile $csvFile = null;
    public bool $updateExisting = false;
    public ?array $importResult = null;
    public bool $selfInvoiceImportEnabled = true;

    public function mount(CompanySettings $company): void
    {
        $this->selfInvoiceImportEnabled = FiscalRegimePolicy::supportsSelfInvoices(
            $company->company_fiscal_regime,
            $company->rf19_self_invoices_enabled,
        );
    }

    public function updatedImportType(): void
    {
        $this->resetValidation();
        $this->xmlFiles = [];
        $this->csvFile = null;
        $this->updateExisting = false;
    }

    public function import(): void
    {
        $this->importResult = null;

        match ($this->importType) {
            'xml_sales' => $this->importXml('sales'),
            'xml_purchase' => $this->importXml('purchase'),
            'xml_self_invoice' => $this->importXml('self_invoice'),
            'fattura24_contacts' => $this->importFattura24Contacts(),
            default => $this->addError('importType', 'Tipo import non valido.'),
        };
    }

    private function importXml(string $category): void
    {
        if ($category === 'self_invoice' && ! $this->selfInvoiceImportEnabled) {
            abort(403, 'Import autofatture disabilitato per il regime fiscale corrente.');
        }

        $this->validate(['xmlFiles' => 'required|array|min:1', 'xmlFiles.*' => 'required|file|mimes:xml,p7m,zip|max:10240']);
        $sequenceId = $this->resolveDefaultSequenceId($category);
        if ($sequenceId === null) {
            $this->addError('xmlFiles', __('app.imports.no_sequence_available'));

            return;
        }

        try {
            $service = app(InvoiceXmlImportService::class);
            foreach ($this->xmlFiles as $file) {
                if (strtolower($file->getClientOriginalExtension()) === 'zip') {
                    $this->importXmlFromZip($service, $file->getRealPath(), $sequenceId, $category);
                } else {
                    $service->importXml(file_get_contents($file->getRealPath()), $sequenceId, $category);
                }
            }
            $this->storeResult($this->importType, $service->getStats(), $service->getErrors(), count($this->xmlFiles), $category);
            $this->xmlFiles = [];
        } catch (\Throwable $exception) {
            $this->addError('xmlFiles', $exception->getMessage());
        }
    }

    private function importFattura24Contacts(): void
    {
        $this->validate(['csvFile' => 'required|file|mimes:csv,txt|max:10240', 'updateExisting' => 'boolean']);

        try {
            $importer = app(Fattura24ContactImporter::class);
            $importer->import($this->csvFile->getRealPath(), $this->updateExisting);
            $this->storeResult('fattura24_contacts', $importer->getStats(), $importer->getErrors(), 1, 'contacts');
            $this->csvFile = null;
        } catch (\Throwable $exception) {
            $this->addError('csvFile', $exception->getMessage());
        }
    }

    private function storeResult(string $type, array $stats, array $errors, int $sourceFilesCount, string $category): void
    {
        app(PostHogTelemetryService::class)->capture('xml_import_completed', [
            'import_type' => $type,
            'import_category' => $category,
            'source_files_count' => $sourceFilesCount,
            'error_count' => count($errors),
        ], request()->user());
        $this->importResult = compact('type', 'stats', 'errors');
    }

    private function importXmlFromZip(InvoiceXmlImportService $service, string $zipPath, int $sequenceId, string $category): void
    {
        $zip = new \ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException(__('app.imports.zip_open_error'));
        }
        $xmlFound = false;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['xml', 'p7m'], true) || str_contains($name, '_metaDato')) {
                continue;
            }
            $content = $zip->getFromIndex($index);
            if ($content === false) {
                continue;
            }
            $xmlFound = true;
            $service->importXml($content, $sequenceId, $category);
        }
        $zip->close();
        if (! $xmlFound) {
            throw new \RuntimeException(__('app.imports.zip_no_xml'));
        }
    }

    private function resolveDefaultSequenceId(string $category): ?int
    {
        $key = match ($category) {
            'sales' => 'default_sequence_sales',
            'purchase' => 'default_sequence_purchase',
            'self_invoice' => 'default_sequence_self_invoice',
        };
        $settings = app(InvoiceSettings::class);

        return $settings->{$key} ?? Sequence::query()->where('type', $category)->orderByDesc('is_system')->value('id');
    }

}; ?>

<x-slot:header><div><p class="text-xs font-bold uppercase tracking-[.12em] text-content-muted">Import data</p><h1 class="text-lg font-bold text-content">Importa documenti e contatti</h1></div></x-slot:header>
<section class="max-w-4xl space-y-6">
    @if($importResult)<article class="rounded-xl border p-5 {{ count($importResult['errors']) ? 'border-amber-200 bg-amber-50' : 'border-success/20 bg-success-bg' }}"><h2 class="font-bold">{{ count($importResult['errors']) ? 'Import completato con errori' : 'Import completato' }}</h2><div class="mt-2 flex flex-wrap gap-4 text-sm">@foreach($importResult['stats'] as $label => $value)<span><strong>{{ $value }}</strong> {{ str_replace('_', ' ', $label) }}</span>@endforeach</div>@if(count($importResult['errors']))<ul class="mt-3 list-disc pl-5 text-sm text-danger">@foreach($importResult['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>@endif</article>@endif
    @if(! $selfInvoiceImportEnabled)<article class="rounded-xl border border-amber-200 bg-amber-50 p-5"><h2 class="font-bold text-amber-900">Autofatture disabilitate per RF19</h2><p class="mt-1 text-sm text-amber-800">Puoi riattivarle da Dati Azienda per le operazioni estero che richiedono TD17, TD18 o TD19.</p></article>@endif
    <form wire:submit="import" class="rounded-xl border border-border-light bg-white p-5 shadow-[var(--shadow-card)]"><div class="grid gap-4 md:grid-cols-2"><label class="block text-sm font-semibold">Tipo import<select wire:model.live="importType" class="mt-2 block w-full rounded-md border border-border px-3 py-2"><option value="xml_sales">Fatture di vendita XML</option><option value="xml_purchase">Fatture di acquisto XML</option>@if($selfInvoiceImportEnabled)<option value="xml_self_invoice">Autofatture XML</option>@endif<option value="fattura24_contacts">Contatti Fattura24 CSV</option></select></label><div class="rounded-md bg-surface-muted p-3 text-sm text-content-muted">@if($importType === 'fattura24_contacts')Carica l'esportazione CSV da Fattura24.@else Carica uno o più file XML, P7M o ZIP, fino a 10 MB ciascuno.@endif</div></div>
        @if($importType === 'fattura24_contacts')<div class="mt-5"><label class="block text-sm font-semibold">File CSV<input wire:model="csvFile" class="mt-2 block w-full text-sm" type="file" accept=".csv,.txt"></label>@error('csvFile')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror<label class="mt-4 flex gap-2 text-sm"><input wire:model="updateExisting" type="checkbox"> Aggiorna contatti esistenti</label></div>@else<div class="mt-5"><label class="block text-sm font-semibold">File XML, P7M o ZIP<input wire:model="xmlFiles" class="mt-2 block w-full text-sm" type="file" accept=".xml,.p7m,.zip" multiple></label>@error('xmlFiles')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror @error('xmlFiles.*')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror</div>@endif
        <button class="mt-5 rounded-md bg-primary px-5 py-2.5 text-sm font-bold text-white" wire:loading.attr="disabled" type="submit"><span wire:loading.remove>Avvia import</span><span wire:loading>Importazione in corso...</span></button>
    </form>
</section>
