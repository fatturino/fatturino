@props(['paymentSummary', 'upcomingDueDates'])

@php
    $unpaidCount  = $paymentSummary['unpaid']['count'] + $paymentSummary['overdue']['count'];
    $unpaidTotal  = $paymentSummary['unpaid']['total'] + $paymentSummary['overdue']['total'];
    $partialCount = $paymentSummary['partial']['count'];
    $partialTotal = $paymentSummary['partial']['total'];
    $paidCount    = $paymentSummary['paid']['count'];
    $paidTotal    = $paymentSummary['paid']['total'];
    $overdueCount = $paymentSummary['overdue']['count'];
@endphp

<x-card :title="__('app.dashboard.payments_title')">

    {{-- Status summary rows --}}
    <div class="space-y-2 mb-4">
        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-error shrink-0"></span>
                <span>{{ __('app.dashboard.payments_unpaid') }}</span>
                @if($overdueCount > 0)
                    <x-badge :value="$overdueCount . ' ' . __('app.dashboard.payments_overdue')" class="badge-soft badge-error badge-xs" />
                @endif
            </div>
            <div class="text-right">
                <span class="font-semibold">€ {{ number_format($unpaidTotal / 100, 2, ',', '.') }}</span>
                <span class="text-base-content/50 ml-1">({{ $unpaidCount }})</span>
            </div>
        </div>

        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-info shrink-0"></span>
                <span>{{ __('app.dashboard.payments_partial') }}</span>
            </div>
            <div class="text-right">
                <span class="font-semibold">€ {{ number_format($partialTotal / 100, 2, ',', '.') }}</span>
                <span class="text-base-content/50 ml-1">({{ $partialCount }})</span>
            </div>
        </div>

        <div class="flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-success shrink-0"></span>
                <span>{{ __('app.dashboard.payments_paid') }}</span>
            </div>
            <div class="text-right">
                <span class="font-semibold">€ {{ number_format($paidTotal / 100, 2, ',', '.') }}</span>
                <span class="text-base-content/50 ml-1">({{ $paidCount }})</span>
            </div>
        </div>
    </div>

    {{-- Upcoming due dates --}}
    @if($upcomingDueDates->isNotEmpty())
        <div class="divider text-xs text-base-content/40 my-2">{{ __('app.dashboard.payments_upcoming') }}</div>
        <div class="space-y-2">
            @foreach($upcomingDueDates as $invoice)
                <a href="/sell-invoices/{{ $invoice->id }}/edit" class="flex items-center justify-between text-sm hover:bg-base-200 rounded-lg px-2 py-1 -mx-2 transition-colors">
                    <div class="flex items-center gap-2 min-w-0">
                        @if($invoice->payment_status === \App\Enums\PaymentStatus::Overdue)
                            <x-icon name="o-exclamation-triangle" class="w-4 h-4 text-error shrink-0" />
                        @else
                            <x-icon name="o-clock" class="w-4 h-4 text-warning shrink-0" />
                        @endif
                        <span class="truncate">{{ $invoice->contact?->name ?? __('app.common.unknown') }}</span>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <span class="font-semibold">€ {{ number_format($invoice->total_gross / 100, 2, ',', '.') }}</span>
                        <span @class([
                            'text-xs',
                            'text-error' => $invoice->due_date->isPast(),
                            'text-base-content/50' => !$invoice->due_date->isPast(),
                        ])>{{ $invoice->due_date->format('d/m') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

</x-card>
