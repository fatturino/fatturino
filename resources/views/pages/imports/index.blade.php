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
        $this->importResult = null;
        $this->dispatch('import-type-changed');
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
        } catch (Throwable $exception) {
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
        } catch (Throwable $exception) {
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
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException(__('app.imports.zip_open_error'));
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
            throw new RuntimeException(__('app.imports.zip_no_xml'));
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
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">Importazioni</p>
        <h1 class="text-lg font-semibold text-content">Importazioni</h1>
    </div>
</x-slot:header>

@php
    $importTypeOptions = ['xml_sales' => 'Fatture di vendita XML', 'xml_purchase' => 'Fatture di acquisto XML'];
    if ($selfInvoiceImportEnabled) {
        $importTypeOptions['xml_self_invoice'] = 'Autofatture XML';
    }
    $importTypeOptions['fattura24_contacts'] = 'Contatti Fattura24 CSV';

    $importDetails = match ($importType) {
        'xml_sales' => ['title' => 'Importa fatture di vendita', 'description' => 'Crea fatture di vendita e i contatti collegati a partire dai file ricevuti.', 'fileLabel' => 'Carica fatture di vendita', 'fileDescription' => 'File XML, P7M o ZIP fino a 10 MB ciascuno.', 'accept' => '.xml,.p7m,.zip', 'multiple' => true],
        'xml_self_invoice' => ['title' => 'Importa autofatture', 'description' => 'Importa documenti TD17–TD29 e conserva i riferimenti alla fattura collegata.', 'fileLabel' => 'Carica autofatture', 'fileDescription' => 'File XML, P7M o ZIP fino a 10 MB ciascuno.', 'accept' => '.xml,.p7m,.zip', 'multiple' => true],
        'fattura24_contacts' => ['title' => 'Importa contatti da Fattura24', 'description' => 'Carica l’esportazione CSV per creare o aggiornare clienti e fornitori.', 'fileLabel' => 'Carica esportazione Fattura24', 'fileDescription' => 'Un file CSV o TXT fino a 10 MB.', 'accept' => '.csv,.txt', 'multiple' => false],
        default => ['title' => 'Importa fatture ricevute', 'description' => 'Crea fatture di acquisto e i fornitori collegati a partire dai file ricevuti.', 'fileLabel' => 'Carica fatture ricevute', 'fileDescription' => 'File XML, P7M o ZIP fino a 10 MB ciascuno.', 'accept' => '.xml,.p7m,.zip', 'multiple' => true],
    };

    $resultLinks = [
        'xml_sales' => ['label' => 'Vai alle fatture di vendita', 'href' => route('sell-invoices.index')],
        'xml_purchase' => ['label' => 'Vai alle fatture di acquisto', 'href' => route('purchase-invoices.index')],
        'xml_self_invoice' => ['label' => 'Vai alle autofatture', 'href' => route('self-invoices.index')],
        'fattura24_contacts' => ['label' => 'Vai ai contatti', 'href' => route('contacts.index')],
    ];

    $statLabels = [
        'total' => 'File o righe elaborati',
        'invoices_imported' => 'Fatture importate',
        'contacts_created' => 'Contatti creati',
        'imported' => 'Contatti importati',
        'updated' => 'Contatti aggiornati',
        'skipped' => 'Elementi saltati',
        'errors' => 'Errori',
    ];
@endphp

<section class="mx-auto max-w-4xl space-y-6">
    <div>
        <h2 class="text-xl font-semibold text-content">{{ $importDetails['title'] }}</h2>
        <p class="mt-1 text-sm text-content-muted">{{ $importDetails['description'] }}</p>
    </div>

    @if($importResult)
        @php
            $hasImportErrors = count($importResult['errors']) > 0;
            $resultLink = $resultLinks[$importResult['type']];
        @endphp
        <article class="rounded-xl border p-5 sm:p-6 {{ $hasImportErrors ? 'border-warning/30 bg-warning/10' : 'border-success/30 bg-success/10' }}" role="status">
            <div class="flex items-start gap-3">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $hasImportErrors ? 'bg-warning/15 text-warning' : 'bg-success/15 text-success' }}" aria-hidden="true"><x-icon :name="$hasImportErrors ? 'o-exclamation-triangle' : 'o-check-circle'" class="size-5" /></div>
                <div class="min-w-0 flex-1">
                    <h2 class="text-base font-semibold text-content">{{ $hasImportErrors ? 'Import completato con elementi da verificare' : 'Import completato' }}</h2>
                    <p class="mt-1 text-sm text-content-muted">{{ $hasImportErrors ? 'Alcuni elementi non sono stati importati: consulta gli errori prima di ripetere l’operazione.' : 'I dati sono disponibili nell’elenco corrispondente.' }}</p>
                </div>
            </div>

            <dl class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($importResult['stats'] as $key => $value)
                    <div class="rounded-lg border border-border/70 bg-white/70 px-3 py-2.5">
                        <dt class="text-xs text-content-muted">{{ $statLabels[$key] ?? str_replace('_', ' ', $key) }}</dt>
                        <dd class="mt-1 text-lg font-semibold tabular-nums text-content">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>

            @if($hasImportErrors)
                <div class="mt-5 border-t border-warning/25 pt-4" role="alert">
                    <h3 class="text-sm font-semibold text-content">Errori da verificare</h3>
                    <ul class="mt-2 space-y-2 text-sm text-content-muted">
                        @foreach($importResult['errors'] as $error)<li class="flex gap-2"><x-icon name="o-x-circle" class="mt-0.5 size-4 shrink-0 text-danger" /> <span>{{ $error }}</span></li>@endforeach
                    </ul>
                </div>
            @endif

            <x-app-link :href="$resultLink['href']" class="mt-5 inline-flex h-10 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">{{ $resultLink['label'] }}</x-app-link>
        </article>
    @endif

    @if(! $selfInvoiceImportEnabled)
        <article class="rounded-xl border border-warning/30 bg-warning/10 p-5 sm:p-6">
            <div class="flex items-start gap-3">
                <x-icon name="o-exclamation-triangle" class="mt-0.5 size-5 shrink-0 text-warning" />
                <div>
                    <h2 class="text-base font-semibold text-content">Autofatture disabilitate per RF19</h2>
                    <p class="mt-1 text-sm text-content-muted">Puoi riattivarle da Dati Azienda per le operazioni estero che richiedono TD17, TD18 o TD19.</p>
                </div>
            </div>
        </article>
    @endif

    <form wire:submit="import" class="rounded-xl border border-border bg-white p-5 sm:p-6">
        <div class="grid gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] md:items-end">
            <x-select label="Tipo import" wire:model.live="importType" :options="$importTypeOptions" />
            <div class="rounded-lg bg-surface-muted p-4 text-sm text-content-muted">
                <p class="font-medium text-content">Prima di iniziare</p>
                <p class="mt-1">{{ $importType === 'fattura24_contacts' ? 'I contatti già presenti vengono saltati, a meno che tu non scelga di aggiornarli.' : 'L’import usa il sezionale configurato per la categoria selezionata e ignora eventuali duplicati.' }}</p>
            </div>
        </div>

        <div class="mt-6">
            @if($importType === 'fattura24_contacts')
                <x-imports.dropzone wire:key="import-dropzone-{{ $importType }}" wire:model="csvFile" label="{{ $importDetails['fileLabel'] }}" description="{{ $importDetails['fileDescription'] }}" :accept="$importDetails['accept']" :multiple="$importDetails['multiple']" error-key="csvFile" target="csvFile" />
                <label class="mt-4 flex items-start gap-3 rounded-lg border border-border p-3 text-sm text-content">
                    <input wire:model="updateExisting" wire:loading.attr="disabled" wire:target="import" type="checkbox" class="mt-0.5 size-4 rounded border-border text-primary focus:ring-primary/20">
                    <span><span class="font-medium">Aggiorna contatti esistenti</span><span class="mt-0.5 block text-content-muted">Usa P. IVA e nazione per aggiornare un contatto già presente invece di saltarlo.</span></span>
                </label>
            @else
                <x-imports.dropzone wire:key="import-dropzone-{{ $importType }}" wire:model="xmlFiles" label="{{ $importDetails['fileLabel'] }}" description="{{ $importDetails['fileDescription'] }}" :accept="$importDetails['accept']" :multiple="$importDetails['multiple']" error-key="xmlFiles" target="xmlFiles" />
            @endif
        </div>

        <div class="mt-6 flex flex-col gap-3 border-t border-border pt-5 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-xs text-content-muted">I file vengono elaborati subito e non viene creato uno storico delle importazioni.</p>
            <button type="submit" wire:loading.attr="disabled" wire:target="import,xmlFiles,csvFile" class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50"><span wire:loading.remove wire:target="import">Avvia importazione</span><span wire:loading wire:target="import" class="inline-flex items-center gap-2"><x-icon name="o-arrow-path" class="size-4 animate-spin" />Importazione in corso...</span></button>
        </div>
    </form>
</section>
