@props([
    'vatCollectedYtd',
    'withholdingTaxYtd',
    'isCurrentYear',
    'fiscalYear',
])

@php
    $periodLabel = $isCurrentYear
        ? __('app.dashboard.year_to_date')
        : __('app.dashboard.full_year', ['year' => $fiscalYear]);
@endphp

<div class="flex flex-col gap-4">

    {{-- VAT collected YTD --}}
    <x-card class="flex-1">
        <div class="flex items-center gap-3">
            <div class="bg-info/10 rounded-xl p-3">
                <x-icon name="o-receipt-percent" class="w-6 h-6 text-info" />
            </div>
            <div>
                <p class="text-xs text-base-content/60 uppercase tracking-wide">{{ __('app.dashboard.vat_collected_ytd') }}</p>
                <p class="text-xl font-bold">€ {{ number_format($vatCollectedYtd / 100, 2, ',', '.') }}</p>
                <p class="text-xs text-base-content/50">{{ $periodLabel }}</p>
            </div>
        </div>
    </x-card>

    {{-- Withholding tax YTD --}}
    <x-card class="flex-1">
        <div class="flex items-center gap-3">
            <div class="bg-warning/10 rounded-xl p-3">
                <x-icon name="o-calculator" class="w-6 h-6 text-warning" />
            </div>
            <div>
                <p class="text-xs text-base-content/60 uppercase tracking-wide">{{ __('app.dashboard.withholding_ytd') }}</p>
                <p class="text-xl font-bold">€ {{ number_format($withholdingTaxYtd / 100, 2, ',', '.') }}</p>
                <p class="text-xs text-base-content/50">{{ $periodLabel }}</p>
            </div>
        </div>
    </x-card>

</div>
