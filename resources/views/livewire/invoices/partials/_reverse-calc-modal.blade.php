{{--
    Reverse calculation modal (Scorporo): enter a desired net-due amount,
    preview the resulting imponibile / VAT / stamp / withholding, then apply
    to the invoice lines.

    Required vars:
      $vatRates : array of VAT options
    Plus flags from host component state consumed by the live preview:
      $fund_enabled, $fund_percent, $fund_vat_rate
      $stamp_duty_applied
      $withholding_tax_enabled, $withholding_tax_percent
      $reverseCalcVatRate, $reverseCalcDesiredNet
--}}
<x-modal wire:model="reverseCalcModal" :title="__('app.invoices.reverse_calc_title')">
    <div class="space-y-4">
        <x-input
            :label="__('app.invoices.reverse_calc_desired_net')"
            wire:model.live.debounce.300ms="reverseCalcDesiredNet"
            type="number"
            step="0.01"
            prefix="€"
            autofocus
        />

        <x-select
            :label="__('app.invoices.reverse_calc_vat_rate')"
            :options="$vatRates"
            option-label="name"
            wire:model.live="reverseCalcVatRate"
        />

        {{-- Live preview --}}
        @if($this->reverseCalcNet > 0)
            <div class="bg-base-200 rounded-lg p-4 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span>{{ __('app.invoices.reverse_calc_result_net') }}</span>
                    <span class="font-semibold">€ {{ number_format($this->reverseCalcNet, 2, ',', '.') }}</span>
                </div>
                @if($fund_enabled && $fund_percent)
                    <div class="flex justify-between text-base-content/70">
                        <span>{{ __('app.invoices.fund_amount_label', ['percent' => $fund_percent]) }}</span>
                        <span>€ {{ number_format($this->reverseCalcNet * (float) $fund_percent / 100, 2, ',', '.') }}</span>
                    </div>
                @endif
                @php
                    $previewVatRate = $reverseCalcVatRate ? \App\Enums\VatRate::tryFrom($reverseCalcVatRate) : null;
                    $previewVatPercent = $previewVatRate ? $previewVatRate->percent() : 0;
                    $previewFundAmount = ($fund_enabled && $fund_percent) ? $this->reverseCalcNet * (float) $fund_percent / 100 : 0;
                    $previewFundVat = ($previewFundAmount > 0 && $fund_vat_rate) ? $previewFundAmount * ((\App\Enums\VatRate::tryFrom($fund_vat_rate)?->percent() ?? 0) / 100) : 0;
                    $previewLineVat = $this->reverseCalcNet * $previewVatPercent / 100;
                    $previewTotalVat = $previewLineVat + $previewFundVat;
                @endphp
                <div class="flex justify-between text-base-content/70">
                    <span>{{ __('app.invoices.vat_total') }}</span>
                    <span>€ {{ number_format($previewTotalVat, 2, ',', '.') }}</span>
                </div>
                @if($stamp_duty_applied)
                    <div class="flex justify-between text-base-content/70">
                        <span>{{ __('app.invoices.stamp_duty_label') }}</span>
                        <span>€ 2,00</span>
                    </div>
                @endif
                @if($withholding_tax_enabled && $withholding_tax_percent)
                    <div class="flex justify-between text-error">
                        <span>{{ __('app.invoices.withholding_tax_amount_label', ['percent' => $withholding_tax_percent]) }}</span>
                        <span>- € {{ number_format($this->reverseCalcNet * (float) $withholding_tax_percent / 100, 2, ',', '.') }}</span>
                    </div>
                @endif
                <hr />
                <div class="flex justify-between font-bold">
                    <span>{{ __('app.invoices.net_due') }}</span>
                    <span>€ {{ number_format((float) $reverseCalcDesiredNet, 2, ',', '.') }}</span>
                </div>
            </div>
        @endif
    </div>

    <x-slot:actions>
        <x-button :label="__('app.common.cancel')" @click="$wire.reverseCalcModal = false" />
        <x-button
            :label="__('app.invoices.reverse_calc_apply')"
            wire:click="applyReverseCalculation"
            class="btn-primary"
            spinner="applyReverseCalculation"
            :disabled="$this->reverseCalcNet <= 0"
        />
    </x-slot:actions>
</x-modal>
