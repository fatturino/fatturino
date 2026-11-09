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
@endphp

<section class="space-y-5">
    <section class="overflow-hidden rounded-xl border border-border bg-border" aria-labelledby="f24-summary-title">
        <div class="flex flex-col gap-4 bg-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-5">
            <div>
                <p class="text-xs font-medium text-content-muted">{{ __('app.f24.summary') }}</p>
                <h2 id="f24-summary-title" class="mt-1 text-base font-semibold text-content">{{ __('app.f24.summary_for_year', ['year' => $fiscalYear]) }}</h2>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-app-link :href="route('f24.create')" class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg bg-primary px-4 text-sm font-medium text-white transition hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary/20">{{ __('app.f24.new') }}</x-app-link>
                <x-app-link :href="route('f24.import')" class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg border border-border bg-white px-4 text-sm font-medium text-content transition hover:border-border-strong hover:bg-surface-muted focus:outline-none focus:ring-2 focus:ring-primary/20">{{ __('app.f24.import_pdf') }}</x-app-link>
            </div>
        </div>
        <dl class="grid grid-cols-1 gap-px bg-border sm:grid-cols-3">
            <div class="min-w-0 bg-white px-4 py-4 sm:px-5">
                <dt class="text-xs font-medium text-content-muted">{{ __('app.f24.paid_ytd') }}</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-content">{{ $this->currency($summary['paid_total_cents']) }}</dd>
            </div>
            <div class="min-w-0 bg-white px-4 py-4 sm:px-5">
                <dt class="text-xs font-medium text-content-muted">{{ __('app.f24.planned_total') }}</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-content">{{ $this->currency($summary['planned_total_cents']) }}</dd>
            </div>
            <div class="min-w-0 bg-white px-4 py-4 sm:px-5">
                <dt class="text-xs font-medium text-content-muted">{{ __('app.f24.overdue_total') }}</dt>
                <dd @class(['mt-1 text-xl font-semibold tabular-nums', $summary['overdue_total_cents'] > 0 ? 'text-danger' : 'text-content'])>{{ $this->currency($summary['overdue_total_cents']) }}</dd>
            </div>
        </dl>
        @if($summary['by_category'] !== [])
            <div class="flex flex-wrap gap-3 border-t border-border bg-white px-4 py-3 text-xs text-content-muted sm:px-5">
                @foreach($summary['by_category'] as $cat => $amount)
                    <span><span class="font-medium text-content">{{ \App\Enums\F24Category::from($cat)->label() }}:</span> {{ $this->currency($amount) }}</span>
                @endforeach
            </div>
        @endif
    </section>

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
        <button type="button" wire:click="resetFilters" class="inline-flex h-11 items-center justify-center rounded-lg px-3 text-sm font-medium text-content-muted transition hover:bg-surface-muted hover:text-content focus:outline-none focus:ring-2 focus:ring-primary/20">{{ __('app.common.reset') }}</button>
    </div>

    <div class="overflow-x-auto border-y border-border bg-white">
        @if($payments->isEmpty())
            <div class="px-5 py-10 text-center">
                <p class="font-medium text-content">{{ __('app.f24.empty_title') }}</p>
                <p class="mt-1 text-sm text-content-muted">{{ __('app.f24.empty_body') }}</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <x-app-link :href="route('f24.create')" class="inline-flex h-10 items-center justify-center rounded-lg bg-primary px-3 text-sm font-medium text-white transition hover:bg-primary-hover">{{ __('app.f24.new') }}</x-app-link>
                    <x-app-link :href="route('f24.import')" class="inline-flex h-10 items-center justify-center rounded-lg border border-border bg-white px-3 text-sm font-medium text-content transition hover:bg-surface-muted">{{ __('app.f24.import_pdf') }}</x-app-link>
                </div>
            </div>
        @else
            <table class="min-w-full text-left text-sm">
                <thead class="border-b border-border bg-surface-muted text-content-muted">
                    <tr>
                        <th class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_label') }}</th>
                        <th class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_status') }}</th>
                        <th class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_due') }}</th>
                        <th class="px-5 py-3 text-xs font-medium">{{ __('app.f24.col_paid') }}</th>
                        <th class="px-5 py-3 text-right text-xs font-medium">{{ __('app.f24.col_amount') }}</th>
                        <th class="px-5 py-3 text-xs font-medium"><span class="sr-only">{{ __('app.common.filters') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $payment)
                        <tr wire:key="f24-{{ $payment->id }}" class="border-b border-border transition-colors hover:bg-surface-muted/70">
                            <td class="px-5 py-3">
                                <p class="font-medium text-content">{{ $payment->primaryLabel() }}</p>
                                <p class="text-xs text-content-muted">
                                    {{ $payment->lines->count() }} {{ $payment->lines->count() === 1 ? __('app.f24.one_line') : __('app.f24.many_lines') }}
                                    @if($payment->protocol_number)
                                        · {{ $payment->protocol_number }}
                                    @endif
                                </p>
                            </td>
                            <td class="px-5 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                    'bg-success-bg text-success' => $payment->status === \App\Enums\F24PaymentStatus::Paid,
                                    'bg-danger-bg text-danger' => $payment->status === \App\Enums\F24PaymentStatus::Planned && $payment->isOverdue(),
                                    'bg-warning-bg text-warning' => $payment->status === \App\Enums\F24PaymentStatus::Planned && ! $payment->isOverdue(),
                                    'bg-surface-muted text-content-muted' => $payment->status === \App\Enums\F24PaymentStatus::Cancelled,
                                ])>
                                    {{ $payment->isOverdue() ? __('app.f24.status_overdue') : $payment->status->label() }}
                                </span>
                            </td>
                            <td class="px-5 py-3 tabular-nums text-content-muted">{{ $payment->due_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-5 py-3 tabular-nums text-content-muted">{{ $payment->paid_at?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-5 py-3 text-right font-medium tabular-nums text-content">{{ $this->currency((int) $payment->balance_cents) }}</td>
                            <td class="px-5 py-3">
                                <div class="flex flex-wrap justify-end gap-3">
                                    <x-app-link :href="route('f24.edit', $payment)" class="text-sm font-medium text-primary hover:underline">{{ __('app.f24.edit') }}</x-app-link>
                                    @if($payment->status === \App\Enums\F24PaymentStatus::Planned)
                                        <button type="button" wire:click="markPaid({{ $payment->id }})" class="text-sm font-medium text-success hover:underline">{{ __('app.f24.mark_paid') }}</button>
                                    @endif
                                    <button type="button" wire:click="delete({{ $payment->id }})" wire:confirm="{{ __('app.common.confirm_delete') }}" class="text-sm font-medium text-danger hover:underline">{{ __('app.common.delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="border-t border-border px-5 py-3">{{ $payments->links() }}</div>
        @endif
    </div>
</section>
