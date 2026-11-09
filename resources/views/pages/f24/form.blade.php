<?php

use App\Enums\F24Category;
use App\Enums\F24PaymentSource;
use App\Enums\F24PaymentStatus;
use App\Enums\F24Section;
use App\Models\F24Payment;
use App\Services\F24PaymentWriter;
use App\Settings\CompanySettings;
use App\Support\F24TributeCode;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public ?int $paymentId = null;

    public ?string $publicId = null;

    public string $status = 'planned';

    public string $source = 'manual';

    public string $due_date = '';

    public string $paid_at = '';

    public string $protocol_number = '';

    public string $notes = '';

    public ?string $source_file_path = null;

    public ?string $source_file_name = null;

    public ?string $source_file_mime = null;

    public ?array $ocr_raw = null;

    public ?string $ocr_warning = null;

    /** @var list<array<string, mixed>> */
    public array $lines = [];

    public string $codeSearch = '';

    public ?int $catalogTargetIndex = null;

    public function mount(?F24Payment $f24Payment = null): void
    {
        if ($f24Payment?->exists) {
            $this->paymentId = $f24Payment->id;
            $this->publicId = $f24Payment->public_id;
            $this->status = $f24Payment->status->value;
            $this->source = $f24Payment->source->value;
            $this->due_date = $f24Payment->due_date?->format('Y-m-d') ?? '';
            $this->paid_at = $f24Payment->paid_at?->format('Y-m-d') ?? '';
            $this->protocol_number = (string) ($f24Payment->protocol_number ?? '');
            $this->notes = (string) ($f24Payment->notes ?? '');
            $this->source_file_path = $f24Payment->source_file_path;
            $this->source_file_name = $f24Payment->source_file_name;
            $this->source_file_mime = $f24Payment->source_file_mime;
            $this->ocr_raw = $f24Payment->ocr_raw;
            $this->lines = $f24Payment->lines->map(fn ($line) => [
                'section' => $line->section->value,
                'category' => $line->category->value,
                'tribute_code' => (string) ($line->tribute_code ?? ''),
                'inps_causal' => (string) ($line->inps_causal ?? ''),
                'reference_year' => (string) ($line->reference_year ?? ''),
                'installment_or_month' => (string) ($line->installment_or_month ?? ''),
                'inps_period_from' => (string) ($line->inps_period_from ?? ''),
                'inps_period_to' => (string) ($line->inps_period_to ?? ''),
                'debit_euros' => number_format(((int) $line->debit_cents) / 100, 2, ',', ''),
                'credit_euros' => number_format(((int) $line->credit_cents) / 100, 2, ',', ''),
                'label' => (string) ($line->label ?? ''),
            ])->all();
        } elseif (session()->has('f24_import_draft')) {
            $draft = session()->pull('f24_import_draft');
            $this->publicId = isset($draft['public_id']) ? strtolower((string) $draft['public_id']) : null;
            $this->source = F24PaymentSource::Ocr->value;
            $this->status = F24PaymentStatus::Paid->value;
            $this->due_date = (string) ($draft['due_date'] ?? '');
            $this->paid_at = (string) ($draft['paid_at'] ?? now()->toDateString());
            $this->protocol_number = (string) ($draft['protocol_number'] ?? '');
            $this->source_file_path = $draft['source_file_path'] ?? null;
            $this->source_file_name = $draft['source_file_name'] ?? null;
            $this->source_file_mime = $draft['source_file_mime'] ?? null;
            $this->ocr_raw = $draft['ocr_raw'] ?? null;
            $this->ocr_warning = $draft['ocr_warning'] ?? null;
            $this->lines = $draft['lines'] ?? [];
        }

        if ($this->lines === []) {
            $this->addLine(F24Section::Erario->value);
        }
    }

    public function addLine(string $section = 'erario'): void
    {
        $this->lines[] = $this->emptyLine($section);
        $this->catalogTargetIndex = array_key_last($this->lines);
        $this->codeSearch = '';
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        if ($this->catalogTargetIndex === $index) {
            $this->catalogTargetIndex = null;
            $this->codeSearch = '';
        }
    }

    public function openCatalog(int $index): void
    {
        $this->catalogTargetIndex = $index;
        $this->codeSearch = '';
    }

    public function applyCatalogCode(int $index, string $code): void
    {
        $entry = F24TributeCode::find($code);
        if ($entry === null || ! isset($this->lines[$index])) {
            return;
        }

        $this->lines[$index]['section'] = $entry['section'];
        $this->lines[$index]['category'] = $entry['category'];
        $this->lines[$index]['label'] = $entry['label'];

        if ($entry['section'] === 'inps') {
            $this->lines[$index]['inps_causal'] = $entry['code'];
            $this->lines[$index]['tribute_code'] = '';
        } else {
            $this->lines[$index]['tribute_code'] = $entry['code'];
            $this->lines[$index]['inps_causal'] = '';
        }

        $this->codeSearch = '';
        $this->catalogTargetIndex = null;
    }

    public function save(F24PaymentWriter $writer): mixed
    {
        $validated = $this->validate($this->rules());

        if ($validated['status'] === F24PaymentStatus::Paid->value && blank($validated['paid_at'])) {
            $validated['paid_at'] = now()->toDateString();
        }

        $payload = [
            'public_id' => $this->publicId,
            'source' => $this->source,
            'status' => $validated['status'],
            'due_date' => $validated['due_date'] ?: null,
            'paid_at' => $validated['paid_at'] ?: null,
            'protocol_number' => $validated['protocol_number'] ?: null,
            'notes' => $validated['notes'] ?: null,
            'source_file_path' => $this->source_file_path,
            'source_file_name' => $this->source_file_name,
            'source_file_mime' => $this->source_file_mime,
            'ocr_raw' => $this->ocr_raw,
            'lines' => array_map(function (array $line) {
                return [
                    'section' => $line['section'],
                    'category' => $line['category'],
                    'tribute_code' => $line['tribute_code'] ?: null,
                    'inps_causal' => $line['inps_causal'] ?: null,
                    'reference_year' => $line['reference_year'] ?: null,
                    'installment_or_month' => $line['installment_or_month'] ?: null,
                    'inps_period_from' => $line['inps_period_from'] ?: null,
                    'inps_period_to' => $line['inps_period_to'] ?: null,
                    'debit_cents' => $this->eurosToCents($line['debit_euros'] ?? '0'),
                    'credit_cents' => $this->eurosToCents($line['credit_euros'] ?? '0'),
                    'label' => $line['label'] ?: null,
                ];
            }, $validated['lines']),
        ];

        $payment = $this->paymentId
            ? F24Payment::query()->findOrFail($this->paymentId)
            : null;

        $writer->save($payment, $payload);
        session()->flash('success', __('app.f24.saved'));

        return $this->redirectRoute('f24.index', navigate: true);
    }

    public function catalogOptions(?string $section = null): array
    {
        $regime = app(CompanySettings::class)->company_fiscal_regime;
        $options = F24TributeCode::options($this->codeSearch !== '' ? $this->codeSearch : null, regime: $regime);

        if ($section !== null && $section !== '') {
            $options = array_values(array_filter(
                $options,
                fn (array $option) => $option['section'] === $section
            ));
        }

        return $options;
    }

    /**
     * @return array<string, list<int>>
     */
    public function lineIndexesBySection(): array
    {
        $grouped = [
            F24Section::Erario->value => [],
            F24Section::Inps->value => [],
            F24Section::Regioni->value => [],
            F24Section::Imu->value => [],
            F24Section::Other->value => [],
        ];

        foreach ($this->lines as $index => $line) {
            $section = $line['section'] ?? F24Section::Other->value;
            if (! array_key_exists($section, $grouped)) {
                $section = F24Section::Other->value;
            }
            $grouped[$section][] = $index;
        }

        return $grouped;
    }

    protected function rules(): array
    {
        return [
            'status' => ['required', 'in:planned,paid,cancelled'],
            'due_date' => ['nullable', 'date'],
            'paid_at' => ['nullable', 'date'],
            'protocol_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.section' => ['required', 'in:erario,inps,regioni,imu,other'],
            'lines.*.category' => ['required', 'in:irpef,sostitutiva,iva,inps,other'],
            'lines.*.tribute_code' => ['nullable', 'string', 'max:16'],
            'lines.*.inps_causal' => ['nullable', 'string', 'max:16'],
            'lines.*.reference_year' => ['nullable', 'string', 'max:4'],
            'lines.*.installment_or_month' => ['nullable', 'string', 'max:8'],
            'lines.*.inps_period_from' => ['nullable', 'string', 'max:7'],
            'lines.*.inps_period_to' => ['nullable', 'string', 'max:7'],
            'lines.*.debit_euros' => ['nullable', 'string', 'max:20'],
            'lines.*.credit_euros' => ['nullable', 'string', 'max:20'],
            'lines.*.label' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function emptyLine(string $section = 'erario'): array
    {
        $category = match ($section) {
            'inps' => F24Category::Inps->value,
            default => F24Category::Other->value,
        };

        $withInstallment = in_array($section, ['erario', 'regioni', 'imu'], true);

        return [
            'section' => $section,
            'category' => $category,
            'tribute_code' => '',
            'inps_causal' => '',
            'reference_year' => (string) now()->year,
            'installment_or_month' => $withInstallment ? '0101' : '',
            'inps_period_from' => '',
            'inps_period_to' => '',
            'debit_euros' => '',
            'credit_euros' => '',
            'label' => '',
        ];
    }

    private function eurosToCents(?string $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        $normalized = str_replace(['.', ' '], '', $value);
        $normalized = str_replace(',', '.', $normalized);

        return (int) round(((float) $normalized) * 100);
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">{{ __('app.nav.tax') }}</p>
        <h1 class="text-lg font-semibold text-content">{{ $paymentId ? __('app.f24.edit_title') : __('app.f24.create_title') }}</h1>
    </div>
</x-slot:header>

@php
    $fieldClass = 'input-field mt-1.5 block w-full';
    $textareaClass = 'textarea-field input-field mt-1.5 block w-full';
    $grouped = $this->lineIndexesBySection();
    $sections = [
        [
            'key' => 'erario',
            'title' => __('app.f24.section_erario_title'),
            'hint' => __('app.f24.section_erario_hint'),
            'add' => __('app.f24.add_erario_line'),
            'indexes' => $grouped['erario'],
        ],
        [
            'key' => 'inps',
            'title' => __('app.f24.section_inps_title'),
            'hint' => __('app.f24.section_inps_hint'),
            'add' => __('app.f24.add_inps_line'),
            'indexes' => $grouped['inps'],
        ],
        [
            'key' => 'regioni',
            'title' => __('app.f24.section_regioni_title'),
            'hint' => __('app.f24.section_regioni_hint'),
            'add' => __('app.f24.add_regioni_line'),
            'indexes' => $grouped['regioni'],
        ],
        [
            'key' => 'imu',
            'title' => __('app.f24.section_imu_title'),
            'hint' => __('app.f24.section_imu_hint'),
            'add' => __('app.f24.add_imu_line'),
            'indexes' => $grouped['imu'],
        ],
        [
            'key' => 'other',
            'title' => __('app.f24.section_other_title'),
            'hint' => __('app.f24.section_other_hint'),
            'add' => __('app.f24.add_other_line'),
            'indexes' => $grouped['other'],
        ],
    ];
@endphp

<form wire:submit="save" class="mx-auto max-w-5xl space-y-6 pb-24">
    @if($ocr_warning)
        <div class="rounded-lg border border-warning/30 bg-warning-bg px-4 py-3 text-sm text-warning" role="status">{{ $ocr_warning }}</div>
    @endif

    @if($source_file_name)
        <div class="rounded-xl border border-border bg-white px-4 py-3 text-sm text-content-muted sm:px-5">
            {{ __('app.f24.attached_file') }}: <span class="font-medium text-content">{{ $source_file_name }}</span>
            @if($source_file_path)
                · <a href="{{ route('f24.attachment', ['path' => $source_file_path]) }}" class="font-medium text-primary hover:underline" target="_blank" rel="noopener">{{ __('app.f24.open_attachment') }}</a>
            @endif
        </div>
    @endif

    <article class="rounded-xl border border-border bg-white p-5 sm:p-6">
        <h2 class="text-base font-semibold text-content">{{ __('app.f24.payment_details') }}</h2>
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="block text-sm font-medium text-content">
                {{ __('app.f24.col_status') }}
                <select wire:model="status" class="{{ $fieldClass }}">
                    <option value="planned">{{ __('app.f24.status_planned') }}</option>
                    <option value="paid">{{ __('app.f24.status_paid') }}</option>
                    <option value="cancelled">{{ __('app.f24.status_cancelled') }}</option>
                </select>
                @error('status') <span class="mt-1 block text-xs text-danger">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm font-medium text-content">
                {{ __('app.f24.col_due') }}
                <input type="date" wire:model="due_date" class="{{ $fieldClass }}">
            </label>
            <label class="block text-sm font-medium text-content">
                {{ __('app.f24.col_paid') }}
                <input type="date" wire:model="paid_at" class="{{ $fieldClass }}">
            </label>
            <label class="block text-sm font-medium text-content sm:col-span-2">
                {{ __('app.f24.protocol') }}
                <input type="text" wire:model="protocol_number" class="{{ $fieldClass }}" maxlength="64">
            </label>
            <label class="block text-sm font-medium text-content sm:col-span-2 lg:col-span-3">
                {{ __('app.f24.notes') }}
                <textarea wire:model="notes" rows="3" class="{{ $textareaClass }}"></textarea>
            </label>
        </div>
    </article>

    <div class="space-y-5">
        @foreach($sections as $section)
            <section class="f24-section" wire:key="f24-section-{{ $section['key'] }}" aria-labelledby="f24-section-{{ $section['key'] }}-title">
                <header class="f24-section__header">
                    <div>
                        <h2 id="f24-section-{{ $section['key'] }}-title" class="f24-section__title">{{ $section['title'] }}</h2>
                        <p class="f24-section__hint">{{ $section['hint'] }}</p>
                    </div>
                    <button type="button" wire:click="addLine('{{ $section['key'] }}')" class="f24-section__add">{{ $section['add'] }}</button>
                </header>

                <div class="f24-section__body">
                    @forelse($section['indexes'] as $index)
                        @php($line = $lines[$index])
                        <div wire:key="line-{{ $index }}" class="f24-section__row">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-xs font-medium text-content-muted">{{ __('app.f24.line_n', ['n' => $loop->iteration]) }}</p>
                                <button type="button" wire:click="removeLine({{ $index }})" class="text-sm font-medium text-danger hover:underline">{{ __('app.common.delete') }}</button>
                            </div>

                            <div class="mb-4">
                                <label class="block text-sm font-medium text-content">
                                    {{ __('app.f24.catalog_search') }}
                                    <input
                                        type="search"
                                        wire:model.live.debounce.300ms="codeSearch"
                                        wire:focus="openCatalog({{ $index }})"
                                        class="{{ $fieldClass }}"
                                        placeholder="{{ __('app.f24.catalog_placeholder') }}"
                                    >
                                </label>
                                @if($catalogTargetIndex === $index && $codeSearch !== '')
                                    <ul class="mt-2 max-h-40 overflow-y-auto rounded-lg border border-border bg-white text-sm">
                                        @forelse($this->catalogOptions($section['key']) as $option)
                                            <li>
                                                <button type="button" wire:click="applyCatalogCode({{ $index }}, '{{ $option['id'] }}')" class="block w-full px-3 py-2 text-left hover:bg-primary-subtle">{{ $option['name'] }}</button>
                                            </li>
                                        @empty
                                            <li class="px-3 py-2 text-content-muted">{{ __('app.f24.catalog_empty') }}</li>
                                        @endforelse
                                    </ul>
                                @endif
                            </div>

                            @if($section['key'] === 'inps')
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.inps_causal') }}
                                        <input type="text" wire:model="lines.{{ $index }}.inps_causal" class="{{ $fieldClass }}" maxlength="16">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.inps_period_from') }}
                                        <input type="text" wire:model="lines.{{ $index }}.inps_period_from" class="{{ $fieldClass }}" maxlength="7" placeholder="MM/AAAA">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.inps_period_to') }}
                                        <input type="text" wire:model="lines.{{ $index }}.inps_period_to" class="{{ $fieldClass }}" maxlength="7" placeholder="MM/AAAA">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.debit') }}
                                        <input type="text" wire:model="lines.{{ $index }}.debit_euros" class="{{ $fieldClass }}" inputmode="decimal" placeholder="0,00">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.credit') }}
                                        <input type="text" wire:model="lines.{{ $index }}.credit_euros" class="{{ $fieldClass }}" inputmode="decimal" placeholder="0,00">
                                    </label>
                                    <label class="block text-sm font-medium text-content sm:col-span-2 lg:col-span-3">
                                        {{ __('app.f24.line_label') }}
                                        <input type="text" wire:model="lines.{{ $index }}.label" class="{{ $fieldClass }}">
                                    </label>
                                </div>
                            @else
                                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.tribute_code') }}
                                        <input type="text" wire:model="lines.{{ $index }}.tribute_code" class="{{ $fieldClass }}" maxlength="16">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.filter_category') }}
                                        <select wire:model="lines.{{ $index }}.category" class="{{ $fieldClass }}">
                                            @foreach(\App\Enums\F24Category::cases() as $category)
                                                @if($category !== \App\Enums\F24Category::Inps)
                                                    <option value="{{ $category->value }}">{{ $category->label() }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.reference_year') }}
                                        <input type="text" wire:model="lines.{{ $index }}.reference_year" class="{{ $fieldClass }}" maxlength="4">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.installment') }}
                                        <input type="text" wire:model="lines.{{ $index }}.installment_or_month" class="{{ $fieldClass }}" maxlength="8">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.debit') }}
                                        <input type="text" wire:model="lines.{{ $index }}.debit_euros" class="{{ $fieldClass }}" inputmode="decimal" placeholder="0,00">
                                    </label>
                                    <label class="block text-sm font-medium text-content">
                                        {{ __('app.f24.credit') }}
                                        <input type="text" wire:model="lines.{{ $index }}.credit_euros" class="{{ $fieldClass }}" inputmode="decimal" placeholder="0,00">
                                    </label>
                                    <label class="block text-sm font-medium text-content sm:col-span-2 lg:col-span-3">
                                        {{ __('app.f24.line_label') }}
                                        <input type="text" wire:model="lines.{{ $index }}.label" class="{{ $fieldClass }}">
                                    </label>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="f24-section__empty">{{ __('app.f24.section_empty') }}</p>
                    @endforelse
                </div>
            </section>
        @endforeach
        @error('lines') <p class="text-sm text-danger">{{ $message }}</p> @enderror
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3">
        <x-app-link :href="route('f24.index')" class="inline-flex h-11 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">{{ __('app.common.cancel') }}</x-app-link>
        <button type="submit" wire:loading.attr="disabled" class="inline-flex h-11 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:cursor-not-allowed disabled:opacity-50">{{ __('app.f24.confirm_save') }}</button>
    </div>
    <p class="text-xs text-content-muted">{{ __('app.f24.human_review_note') }}</p>
</form>
