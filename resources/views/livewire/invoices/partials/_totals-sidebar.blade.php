{{--
    Sidebar totals card: net, fund, VAT, stamp duty, grand total, withholding,
    split payment, net due. Reads computed properties from the host component.

    Required flags (from host component state):
      $fund_enabled, $fund_percent
      $stamp_duty_applied
      $withholding_tax_enabled, $withholding_tax_percent
    Optional flags:
      $split_payment — omit for doc types that don't support split payment (default false)
--}}
@php
    // split_payment is invoice-only; default to false for other document types
    $split_payment = $split_payment ?? false;
@endphp
<div class="bg-base-100 rounded-xl border border-base-200 p-5">
    <div class="space-y-2">
        <div class="flex justify-between text-sm">
            <span class="text-base-content/70">{{ __('app.invoices.net_total') }}</span>
            <span>€ {{ number_format($this->totalNet, 2, ',', '.') }}</span>
        </div>
        @if($fund_enabled && $this->fundAmount > 0)
            <div class="flex justify-between text-sm">
                <span class="text-base-content/70">{{ __('app.invoices.fund_amount_label', ['percent' => $fund_percent]) }}</span>
                <span>€ {{ number_format($this->fundAmount, 2, ',', '.') }}</span>
            </div>
        @endif
        <div class="flex justify-between text-sm">
            <span class="text-base-content/70">{{ __('app.invoices.vat_total') }}</span>
            <span>€ {{ number_format($this->totalVat, 2, ',', '.') }}</span>
        </div>
        @if($stamp_duty_applied)
            <div class="flex justify-between text-sm">
                <span class="text-base-content/70">{{ __('app.invoices.stamp_duty_label') }}</span>
                <span>€ 2,00</span>
            </div>
        @endif

        <hr class="my-1" />

        <div class="flex justify-between font-bold text-lg">
            <span>{{ __('app.invoices.grand_total') }}</span>
            <span>€ {{ number_format($this->totalDue, 2, ',', '.') }}</span>
        </div>

        @if($withholding_tax_enabled)
            <div class="flex justify-between text-sm text-error">
                <span>{{ __('app.invoices.withholding_tax_amount_label', ['percent' => $withholding_tax_percent]) }}</span>
                <span>- € {{ number_format($this->withholdingTaxAmount, 2, ',', '.') }}</span>
            </div>
        @endif
        @if($split_payment)
            <div class="flex justify-between text-sm text-warning">
                <span>{{ __('app.invoices.split_payment_vat_line') }}</span>
                <span>- € {{ number_format($this->totalVat - $this->fundVatAmount, 2, ',', '.') }}</span>
            </div>
        @endif
        @if($withholding_tax_enabled || $split_payment)
            <div class="flex justify-between font-semibold pt-1 border-t">
                <span>{{ __('app.invoices.net_due') }}</span>
                <span>€ {{ number_format($this->netDue, 2, ',', '.') }}</span>
            </div>
        @endif
    </div>
</div>
