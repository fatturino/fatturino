<?php

use App\Enums\F24Category;
use App\Enums\F24PaymentStatus;
use App\Models\F24Payment;
use App\Services\F24PaymentWriter;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] class extends Component {
    use WithPagination;

    public int $fiscalYear;

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'category', except: '')]
    public string $category = '';

    public function mount(): void
    {
        $this->fiscalYear = (int) session('fiscal_year', now()->year);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'category'], true)) {
            $this->resetPage();
        }
    }

    public function markPaid(int $id): void
    {
        $payment = F24Payment::query()->findOrFail($id);
        if ($payment->status !== F24PaymentStatus::Planned) {
            return;
        }

        app(F24PaymentWriter::class)->markPaid($payment);
        session()->flash('success', __('app.f24.marked_paid'));
    }

    public function delete(int $id): void
    {
        F24Payment::query()->findOrFail($id)->delete();
        session()->flash('success', __('app.f24.deleted'));
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('status', 'category');
        $this->resetPage();
    }

    public function currency(int $cents): string
    {
        return '€ '.number_format($cents / 100, 2, ',', '.');
    }

    public function statusLabel(F24Payment $payment): string
    {
        return $payment->isOverdue()
            ? __('app.f24.status_overdue')
            : $payment->status->label();
    }

    public function statusTone(F24Payment $payment): string
    {
        if ($payment->status === F24PaymentStatus::Paid) {
            return 'success';
        }

        if ($payment->status === F24PaymentStatus::Planned && $payment->isOverdue()) {
            return 'danger';
        }

        if ($payment->status === F24PaymentStatus::Planned) {
            return 'warning';
        }

        return 'neutral';
    }

    public function isNearDue(F24Payment $payment): bool
    {
        if ($payment->status !== F24PaymentStatus::Planned || $payment->due_date === null) {
            return false;
        }

        return $payment->due_date->copy()->startOfDay()->lte(now()->startOfDay()->addDays(7));
    }

    public function render()
    {
        $payments = $this->query()
            ->with('lines')
            ->orderByRaw("case status when 'planned' then 0 when 'paid' then 1 else 2 end")
            ->orderByDesc('due_date')
            ->orderByDesc('id')
            ->paginate(15);

        $summary = app(ReportService::class)->f24Summary($this->fiscalYear);

        return view('pages::f24.index', [
            'payments' => $payments,
            'summary' => $summary,
            'statuses' => F24PaymentStatus::cases(),
            'categories' => F24Category::cases(),
        ]);
    }

    private function query(): Builder
    {
        return F24Payment::query()
            ->where('status', '!=', F24PaymentStatus::Draft->value)
            ->where(function (Builder $query) {
                $query->whereYear('paid_at', $this->fiscalYear)
                    ->orWhere(function (Builder $query) {
                        $query->whereNull('paid_at')->whereYear('due_date', $this->fiscalYear);
                    })
                    ->orWhere(function (Builder $query) {
                        $query->whereNull('paid_at')->whereNull('due_date')->whereYear('created_at', $this->fiscalYear);
                    });
            })
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->category !== '', fn (Builder $query) => $query->whereHas(
                'lines',
                fn (Builder $lines) => $lines->where('category', $this->category)
            ));
    }
};
?>

<x-slot:header>
    <div>
        <p class="text-xs font-medium text-content-muted">{{ __('app.nav.tax') }}</p>
        <h1 class="text-lg font-semibold text-content">{{ __('app.f24.title') }}</h1>
    </div>
</x-slot:header>

@php
    $fieldClass = 'input-field mt-1.5 block w-full';
    $hasFilters = $status !== '' || $category !== '';
@endphp

<section class="space-y-5">
    <x-page-summary
        :eyebrow="__('app.f24.summary')"
        :title="__('app.f24.summary_for_year', ['year' => $fiscalYear])"
        title-id="f24-summary-title"
        metrics-class="grid grid-cols-1 gap-px bg-border sm:grid-cols-2 lg:grid-cols-4"
    >
        <x-slot:actions>
            <x-button :label="__('app.f24.new')" :link="route('f24.create')" size="cta" class="shrink-0" />
            <x-button :label="__('app.f24.import_pdf')" :link="route('f24.import')" variant="secondary" size="cta" class="shrink-0" />
        </x-slot:actions>

        <x-page-summary.stat :label="__('app.f24.paid_ytd')" :value="$this->currency($summary['paid_total_cents'])" />
        <x-page-summary.stat :label="__('app.f24.planned_total')" :value="$this->currency($summary['planned_total_cents'])" />
        <x-page-summary.stat
            :label="__('app.f24.overdue_total')"
            :value="$this->currency($summary['overdue_total_cents'])"
            :value-class="$summary['overdue_total_cents'] > 0 ? 'text-danger' : 'text-content'"
        />
        <x-page-summary.stat
            :label="__('app.f24.iva_summary')"
            :value="$this->currency($summary['iva_paid_cents'] + $summary['iva_planned_cents'])"
        >
            <p class="mt-1 text-xs text-content-muted">
                {{ __('app.f24.iva_paid') }}: <span class="font-medium tabular-nums text-content">{{ $this->currency($summary['iva_paid_cents']) }}</span>
                · {{ __('app.f24.iva_planned') }}: <span class="font-medium tabular-nums text-content">{{ $this->currency($summary['iva_planned_cents']) }}</span>
            </p>
        </x-page-summary.stat>

        @if($summary['by_category'] !== [])
            <x-slot:footer>
                <div class="flex flex-wrap gap-3 border-t border-border bg-white px-4 py-3 text-xs text-content-muted sm:px-5">
                    @foreach($summary['by_category'] as $cat => $amount)
                        <span><span class="font-medium text-content">{{ \App\Enums\F24Category::from($cat)->label() }}:</span> {{ $this->currency($amount) }}</span>
                    @endforeach
                </div>
            </x-slot:footer>
        @endif
    </x-page-summary>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="grid w-full flex-1 gap-3 sm:grid-cols-2">
            <label class="block text-sm font-medium text-content">
                {{ __('app.f24.filter_status') }}
                <select wire:model.live="status" class="{{ $fieldClass }}">
                    <option value="">{{ __('app.common.all') }}</option>
                    @foreach($statuses as $statusOption)
                        @if($statusOption !== \App\Enums\F24PaymentStatus::Draft)
                            <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                        @endif
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-medium text-content">
                {{ __('app.f24.filter_category') }}
                <select wire:model.live="category" class="{{ $fieldClass }}">
                    <option value="">{{ __('app.common.all') }}</option>
                    @foreach($categories as $categoryOption)
                        <option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if($hasFilters)
            <x-button :label="__('app.common.reset')" wire:click="resetFilters" variant="ghost" size="cta" />
        @endif
    </div>

    <x-loading-banner target="status,category">{{ __('app.f24.updating') }}</x-loading-banner>

    <div class="hidden overflow-x-auto border-y border-border bg-white lg:block">
        <table class="min-w-full text-left text-sm">
            <thead class="border-b border-border bg-surface-muted text-content-muted">
                <tr>
                    <th scope="col" class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_label') }}</th>
                    <th scope="col" class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_status') }}</th>
                    <th scope="col" class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_due') }}</th>
                    <th scope="col" class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_paid') }}</th>
                    <th scope="col" class="px-5 py-3 text-right text-xs font-medium">{{ __('app.f24.col_amount') }}</th>
                    <th scope="col" class="w-14 px-3 py-3 text-right text-xs font-medium"><span class="sr-only">{{ __('app.f24.col_actions') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($payments as $payment)
                    <tr
                        wire:key="f24-{{ $payment->id }}"
                        @class([
                            'transition-colors focus-within:bg-primary-subtle',
                            'bg-danger-bg/50 hover:bg-danger-bg/70' => $payment->isOverdue(),
                            'bg-warning-bg/60 hover:bg-warning-bg/80' => ! $payment->isOverdue() && $this->isNearDue($payment),
                            'hover:bg-surface-muted/70' => ! $payment->isOverdue() && ! $this->isNearDue($payment),
                        ])
                    >
                        <td class="px-5 py-4 align-top">
                            <x-app-link :href="route('f24.edit', $payment)" class="rounded font-medium text-content transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                {{ $payment->primaryLabel() }}
                            </x-app-link>
                            <p class="mt-1 text-xs text-content-muted">
                                {{ $payment->lines->count() }} {{ $payment->lines->count() === 1 ? __('app.f24.one_line') : __('app.f24.many_lines') }}
                                @if($payment->protocol_number)
                                    · {{ $payment->protocol_number }}
                                @endif
                            </p>
                        </td>
                        <td class="px-5 py-4 align-top whitespace-nowrap">
                            <x-badge :value="$this->statusLabel($payment)" :variant="$this->statusTone($payment)" />
                        </td>
                        <td class="px-5 py-4 align-top whitespace-nowrap tabular-nums text-content-muted">{{ $payment->due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-4 align-top whitespace-nowrap tabular-nums text-content-muted">{{ $payment->paid_at?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-5 py-4 align-top text-right font-semibold tabular-nums text-content">{{ $this->currency((int) $payment->balance_cents) }}</td>
                        <td class="px-3 py-4 align-top text-right">
                            <x-f24.f24-actions :payment="$payment" />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-empty-state
                                :title="$hasFilters ? __('app.f24.empty_filtered') : __('app.f24.empty_title')"
                                :body="$hasFilters ? null : __('app.f24.empty_body')"
                            >
                                @if($hasFilters)
                                    <button wire:click="resetFilters" type="button" class="mt-3 text-sm font-medium text-primary underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-primary/20">{{ __('app.common.reset') }}</button>
                                @else
                                    <x-slot:actions>
                                        <x-button :label="__('app.f24.new')" :link="route('f24.create')" size="md" />
                                        <x-button :label="__('app.f24.import_pdf')" :link="route('f24.import')" variant="secondary" size="md" />
                                    </x-slot:actions>
                                @endif
                            </x-empty-state>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="divide-y divide-border border-y border-border bg-white lg:hidden">
        @forelse($payments as $payment)
            <article
                wire:key="f24-mobile-{{ $payment->id }}"
                @class([
                    'p-4',
                    'bg-danger-bg/40' => $payment->isOverdue(),
                    'bg-warning-bg/50' => ! $payment->isOverdue() && $this->isNearDue($payment),
                ])
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <x-app-link :href="route('f24.edit', $payment)" class="block truncate rounded text-sm font-semibold text-content transition hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            {{ $payment->primaryLabel() }}
                        </x-app-link>
                        <p class="mt-1 truncate text-sm text-content-muted">
                            {{ $payment->lines->count() }} {{ $payment->lines->count() === 1 ? __('app.f24.one_line') : __('app.f24.many_lines') }}
                            @if($payment->protocol_number)
                                · {{ $payment->protocol_number }}
                            @endif
                        </p>
                    </div>
                    <div class="flex shrink-0 items-start gap-1">
                        <x-badge :value="$this->statusLabel($payment)" :variant="$this->statusTone($payment)" />
                        <x-f24.f24-actions :payment="$payment" />
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <p class="text-xs text-content-muted">{{ __('app.f24.col_due') }}</p>
                        <p class="mt-1 text-content">{{ $payment->due_date?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-content-muted">{{ __('app.f24.col_amount') }}</p>
                        <p class="mt-1 font-semibold tabular-nums text-content">{{ $this->currency((int) $payment->balance_cents) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-content-muted">{{ __('app.f24.col_paid') }}</p>
                        <p class="mt-1 text-content">{{ $payment->paid_at?->format('d/m/Y') ?? '—' }}</p>
                    </div>
                </div>
            </article>
        @empty
            <x-empty-state
                :title="$hasFilters ? __('app.f24.empty_filtered') : __('app.f24.empty_title')"
                :body="$hasFilters ? null : __('app.f24.empty_body')"
            >
                @if($hasFilters)
                    <button wire:click="resetFilters" type="button" class="mt-3 text-sm font-medium text-primary underline underline-offset-4 focus:outline-none focus:ring-2 focus:ring-primary/20">{{ __('app.common.reset') }}</button>
                @else
                    <x-slot:actions>
                        <x-button :label="__('app.f24.new')" :link="route('f24.create')" size="md" />
                        <x-button :label="__('app.f24.import_pdf')" :link="route('f24.import')" variant="secondary" size="md" />
                    </x-slot:actions>
                @endif
            </x-empty-state>
        @endforelse
    </div>

    @if($payments->hasPages())
        <div class="pt-1">{{ $payments->links() }}</div>
    @endif
</section>
