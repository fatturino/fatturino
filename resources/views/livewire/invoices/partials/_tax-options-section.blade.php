{{--
    Collapsible "Opzioni fiscali" sidebar section: withholding, fund, stamp duty,
    VAT payability, split payment.

    Shows a summary of active options when collapsed, so the user rarely
    needs to expand unless overriding defaults for this specific invoice.

    Required vars (bound from host component state):
      $withholding_tax_enabled, $withholding_tax_percent
      $fund_enabled, $fund_type, $fund_percent, $fund_vat_rate
      $stamp_duty_applied
      $vat_payability, $split_payment
      $vatRates
    Optional vars:
      $isReadOnly    : bool (default false)
      $showVatOptions: bool (default true) — hide vat_payability/split_payment for doc types that don't use them
--}}
@php
    $isReadOnly     = $isReadOnly     ?? false;
    $showVatOptions = $showVatOptions ?? true;

    // Build summary of active tax options for the collapsed state
    $summaryParts = [];
    if ($withholding_tax_enabled) {
        $summaryParts[] = __('app.invoices.withholding_tax_label') . ' ' . rtrim(rtrim($withholding_tax_percent, '0'), '.') . '%';
    }
    if ($fund_enabled) {
        $summaryParts[] = __('app.invoices.fund_label') . ' ' . rtrim(rtrim($fund_percent, '0'), '.') . '%';
    }
    if ($stamp_duty_applied) {
        $summaryParts[] = __('app.invoices.stamp_duty_label');
    }
    $hasActiveOptions = !empty($summaryParts);
@endphp

<div x-data="{ open: false }" class="bg-base-100 rounded-xl border border-base-200">
    <button type="button" @click="open = !open" class="flex items-center justify-between w-full p-4 cursor-pointer">
        <div class="flex-1 min-w-0">
            <span class="text-sm font-medium">{{ __('app.invoices.tax_options_section') }}</span>
            {{-- Summary badges visible when collapsed --}}
            <div x-show="!open" class="mt-1.5 flex flex-wrap gap-1">
                @if($hasActiveOptions)
                    @foreach($summaryParts as $part)
                        <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-medium rounded-full bg-primary/10 text-primary">{{ $part }}</span>
                    @endforeach
                @else
                    <span class="text-xs text-base-content/40">{{ __('app.invoices.no_tax_options_active') }}</span>
                @endif
            </div>
        </div>
        <x-icon name="o-chevron-down" class="w-4 h-4 shrink-0 transition-transform duration-200 ml-2" ::class="open && 'rotate-180'" />
    </button>
    <div x-show="open" x-collapse>
        <div @class(['px-4 pb-4 space-y-3', 'pointer-events-none' => $isReadOnly])>
            <x-checkbox :label="__('app.invoices.withholding_tax_label')" wire:model.live="withholding_tax_enabled" />
            @if($withholding_tax_enabled)
                <x-input :label="__('app.invoices.withholding_tax_percent_label')" wire:model.live="withholding_tax_percent" type="number" step="0.01" suffix="%" />
            @endif

            <x-checkbox :label="__('app.invoices.fund_label')" wire:model.live="fund_enabled" />
            @if($fund_enabled)
                <x-select :label="__('app.invoices.fund_type_label')" :options="\App\Enums\FundType::options()" wire:model.live="fund_type" :placeholder="__('app.common.select')" />
                <x-input :label="__('app.invoices.fund_percent_label')" wire:model.live="fund_percent" type="number" step="0.01" suffix="%" />
                <x-select :label="__('app.invoices.fund_vat_rate_label')" :options="$vatRates" option-label="name" wire:model.live="fund_vat_rate" :placeholder="__('app.common.select')" />
            @endif

            <x-checkbox :label="__('app.invoices.stamp_duty_label')" wire:model.live="stamp_duty_applied" />
            @if($stamp_duty_applied)
                <p class="text-xs text-base-content/60 -mt-2">{{ __('app.invoices.stamp_duty_hint') }}</p>
            @endif

            @if($showVatOptions)
                <div class="border-t border-base-200 pt-3 mt-1">
                    <x-select :label="__('app.invoices.vat_payability_label')" :options="\App\Enums\VatPayability::options()" wire:model.live="vat_payability" />
                    <div class="mt-2">
                        <x-checkbox :label="__('app.invoices.split_payment_label')" wire:model.live="split_payment" />
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
