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

        $warning = $extracted['warning']
            ?? ($parsed['matched'] ? null : __('app.f24.ocr_partial'));

        session()->put('f24_import_draft', [
            'public_id' => $publicId,
            'paid_at' => $parsed['paid_at'] ?? now()->toDateString(),
            'due_date' => $parsed['paid_at'] ?? now()->toDateString(),
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
    <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
        <p class="text-sm text-content-muted">{{ __('app.f24.import_help') }}</p>
        <form wire:submit="process" class="mt-5 space-y-4">
            <label class="block text-sm font-medium text-content">
                {{ __('app.f24.upload_label') }}
                <input type="file" wire:model="upload" accept="application/pdf,image/png,image/jpeg,image/webp" class="input-field mt-1.5 block w-full py-2">
                @error('upload') <span class="mt-1 block text-xs text-danger">{{ $message }}</span> @enderror
            </label>
            <div wire:loading wire:target="upload,process" class="text-sm text-content-muted">{{ __('app.f24.ocr_reading') }}</div>
            <div class="flex flex-wrap justify-end gap-3">
                <x-app-link :href="route('f24.index')" class="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">{{ __('app.common.cancel') }}</x-app-link>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50" wire:loading.attr="disabled">{{ __('app.f24.continue_review') }}</button>
            </div>
        </form>
    </article>
    <p class="text-xs text-content-muted">{{ __('app.f24.human_review_note') }}</p>
</section>
