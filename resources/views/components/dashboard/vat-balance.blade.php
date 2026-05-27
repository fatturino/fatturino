@props([
    'vatCollectedYtd',
    'vatOnPurchasesYtd',
    'vatBalanceYtd',
    'isCurrentYear',
    'fiscalYear',
])

@php
    $periodLabel  = $isCurrentYear
        ? __('app.dashboard.year_to_date')
        : __('app.dashboard.full_year', ['year' => $fiscalYear]);
    $balanceColor = $vatBalanceYtd >= 0 ? 'text-warning' : 'text-success';
    $balanceNote  = $vatBalanceYtd >= 0
        ? __('app.dashboard.vat_balance_owed')
        : __('app.dashboard.vat_balance_credit');
@endphp

<x-card class="h-full">
    <div class="flex items-center gap-2 mb-4">
        <div class="bg-primary/10 rounded-xl p-2.5">
            <x-icon name="o-scale" class="w-5 h-5 text-primary" />
        </div>
        <span class="font-semibold">{{ __('app.dashboard.vat_balance_title') }}</span>
    </div>

    {{-- Collected from sales --}}
    <div class="flex items-center justify-between text-sm mb-2">
        <span class="text-base-content/60">{{ __('app.dashboard.vat_collected_label') }}</span>
        <span class="font-semibold text-info">+ € {{ number_format($vatCollectedYtd / 100, 2, ',', '.') }}</span>
    </div>

    {{-- VAT on purchases --}}
    <div class="flex items-center justify-between text-sm mb-3">
        <span class="text-base-content/60">{{ __('app.dashboard.vat_on_purchases_label') }}</span>
        <span class="font-semibold text-error">- € {{ number_format($vatOnPurchasesYtd / 100, 2, ',', '.') }}</span>
    </div>

    <hr class="border-base-300 my-3">

    {{-- Balance: positive = owed to state (warning), negative = credit (success) --}}
    <div class="flex items-center justify-between">
        <span class="font-semibold">{{ __('app.dashboard.vat_balance_label') }}</span>
        <div class="text-right">
            <span class="text-lg font-bold {{ $balanceColor }}">
                € {{ number_format(abs($vatBalanceYtd) / 100, 2, ',', '.') }}
            </span>
            <p class="text-xs {{ $balanceColor }}">{{ $balanceNote }}</p>
        </div>
    </div>

    <p class="text-xs text-base-content/40 mt-3">{{ $periodLabel }}</p>
</x-card>
