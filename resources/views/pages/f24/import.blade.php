<?php

use App\Services\DocumentStorageService;
use App\Services\F24TextExtractor;
use App\Services\F24TextParser;
use App\Support\Ulid;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] class extends Component {
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $upload = null;

    public ?string $message = null;

    public function process(
        DocumentStorageService $storage,
        F24TextExtractor $extractor,
        F24TextParser $parser,
    ): mixed {
        $this->validate([
            'upload' => ['required', 'file', 'max:10240', 'mimetypes:application/pdf,image/png,image/jpeg,image/webp'],
        ]);

        /** @var TemporaryUploadedFile $file */
        $file = $this->upload;
        $contents = $file->get();
        $originalName = $file->getClientOriginalName();
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $year = (int) session('fiscal_year', now()->year);
        $publicId = Ulid::generate();
        $safeName = Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'f24';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'pdf');
        $filename = $safeName.'.'.$extension;

        $path = $storage->storeF24Attachment($contents, $year, $publicId, $filename);

        $extracted = $storage->withLocalPath(
            $path,
            fn (string $absolute) => $extractor->extract($absolute, $mime),
        ) ?? [
            'text' => '',
            'method' => 'skipped',
            'warning' => __('app.f24.ocr_tools_missing'),
            'raw' => [],
        ];

        $parsed = $parser->parse($extracted['text'] ?? '');

        $lines = [];
        foreach ($parsed['lines'] as $line) {
            $lines[] = [
                'section' => $line['section'] ?? 'erario',
                'category' => $line['category'] ?? 'other',
                'tribute_code' => (string) ($line['tribute_code'] ?? ''),
                'inps_causal' => (string) ($line['inps_causal'] ?? ''),
                'reference_year' => (string) ($line['reference_year'] ?? ''),
                'installment_or_month' => (string) ($line['installment_or_month'] ?? ''),
                'inps_period_from' => (string) ($line['inps_period_from'] ?? ''),
                'inps_period_to' => (string) ($line['inps_period_to'] ?? ''),
                'debit_euros' => number_format(((int) ($line['debit_cents'] ?? 0)) / 100, 2, ',', ''),
                'credit_euros' => number_format(((int) ($line['credit_cents'] ?? 0)) / 100, 2, ',', ''),
                'label' => (string) ($line['label'] ?? ''),
            ];
        }

        $guessedDate = $parsed['paid_at']
            ?? $parser->guessDateFromFilename($originalName)
            ?? $parser->guessDueDateFromIvaLines($parsed['lines']);

        $warning = $extracted['warning']
            ?? ($parsed['matched'] ? null : __('app.f24.ocr_partial'));
        if ($parsed['paid_at'] === null && $guessedDate !== null) {
            $warning = $warning ?? __('app.f24.date_from_filename');
        }

        session()->put('f24_import_draft', [
            'public_id' => $publicId,
            'paid_at' => $guessedDate,
            'due_date' => $guessedDate,
            'protocol_number' => $parsed['protocol_number'] ?? null,
            'source_file_path' => $path,
            'source_file_name' => $originalName,
            'source_file_mime' => $mime,
            'ocr_raw' => [
                'method' => $extracted['method'],
                'tools' => $extracted['raw']['tools'] ?? [],
                'text_excerpt' => Str::limit($extracted['text'] ?? '', 2000),
            ],
            'ocr_warning' => $warning,
            'lines' => $lines,
        ]);

        return $this->redirectRoute('f24.create', navigate: true);
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">{{ __('app.nav.tax') }}</p>
        <h1 class="text-lg font-semibold text-content">{{ __('app.f24.import_title') }}</h1>
    </div>
</x-slot:header>

<section class="mx-auto max-w-xl space-y-5">
    <x-settings.panel>
        <p class="text-sm text-content-muted">{{ __('app.f24.import_help') }}</p>
        <form wire:submit="process" class="mt-5 space-y-4">
            <x-imports.dropzone
                wire:model="upload"
                :label="__('app.f24.upload_label')"
                description="PDF o immagine fino a 10 MB."
                accept="application/pdf,image/png,image/jpeg,image/webp"
                error-key="upload"
                target="upload"
            />
            <div wire:loading wire:target="upload,process" class="text-sm text-content-muted">{{ __('app.f24.ocr_reading') }}</div>
            <div class="flex flex-wrap justify-end gap-3">
                <x-button :label="__('app.common.cancel')" :link="route('f24.index')" variant="secondary" size="cta" />
                <x-button type="submit" :label="__('app.f24.continue_review')" size="cta" spinner="process" />
            </div>
        </form>
    </x-settings.panel>
    <p class="text-xs text-content-muted">{{ __('app.f24.human_review_note') }}</p>
</section>
