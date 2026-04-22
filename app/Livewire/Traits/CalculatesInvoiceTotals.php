<?php

namespace App\Livewire\Traits;

use App\Enums\FundType;
use App\Enums\VatRate;

/**
 * Shared totals computation for invoice Create/Edit Livewire components.
 *
 * Requires the host component to expose:
 *   - array  $lines
 *   - bool   $fund_enabled, string $fund_percent, ?string $fund_vat_rate, ?string $fund_type
 *   - bool   $withholding_tax_enabled, string $withholding_tax_percent
 *   - bool   $stamp_duty_applied, bool $auto_stamp_duty, string $stamp_duty_threshold
 *   - bool   $split_payment
 * plus the methods from {@see ManagesInvoiceLines} (notably lineDiscountedTotal).
 */
trait CalculatesInvoiceTotals
{
    /**
     * Auto-toggle stamp duty when line values change and auto_stamp_duty is enabled.
     */
    public function updatedLines(): void
    {
        if ($this->auto_stamp_duty) {
            $this->stamp_duty_applied = $this->stampDutyEligible;
        }
    }

    /**
     * Auto-fill fund percentage when fund type changes.
     */
    public function updatedFundType(): void
    {
        if ($this->fund_type) {
            $type = FundType::tryFrom($this->fund_type);
            if ($type) {
                $this->fund_percent = $type->defaultPercent();
            }
        }
    }

    public function getTotalNetProperty(): float
    {
        $total = 0.0;
        foreach ($this->lines as $line) {
            $total += $this->lineDiscountedTotal($line);
        }

        return $total;
    }

    public function getTotalVatProperty(): float
    {
        $total = 0.0;
        foreach ($this->lines as $line) {
            $vatRate = VatRate::tryFrom($line['vat_rate'] ?? '');
            if ($vatRate) {
                $lineTotal = $this->lineDiscountedTotal($line);
                // Round per line to match cent-level rounding in Invoice::calculateTotals()
                $total += round($lineTotal * ($vatRate->percent() / 100), 2);
            }
        }

        // Add VAT on fund contribution (rivalsa)
        $total += $this->fundVatAmount;

        return $total;
    }

    /**
     * Fund contribution amount (rivalsa previdenziale).
     */
    public function getFundAmountProperty(): float
    {
        if (! $this->fund_enabled || ! $this->fund_percent) {
            return 0.0;
        }

        return round($this->totalNet * ((float) $this->fund_percent / 100), 2);
    }

    /**
     * VAT on fund contribution.
     */
    public function getFundVatAmountProperty(): float
    {
        if ($this->fundAmount <= 0 || ! $this->fund_vat_rate) {
            return 0.0;
        }

        $vatRate = VatRate::tryFrom($this->fund_vat_rate);

        return $vatRate ? round($this->fundAmount * ($vatRate->percent() / 100), 2) : 0.0;
    }

    public function getTotalGrossProperty(): float
    {
        return $this->totalNet + $this->fundAmount + $this->totalVat;
    }

    /**
     * Total amount the client must pay (gross + stamp duty if applied).
     */
    public function getTotalDueProperty(): float
    {
        $total = $this->totalGross;

        if ($this->stamp_duty_applied) {
            $total += 2.00;
        }

        return $total;
    }

    /**
     * Withholding tax calculated live on the net amount (imponibile).
     */
    public function getWithholdingTaxAmountProperty(): float
    {
        if (! $this->withholding_tax_enabled || ! $this->withholding_tax_percent) {
            return 0.0;
        }

        return round($this->totalNet * ((float) $this->withholding_tax_percent / 100), 2);
    }

    /**
     * Net amount due to pay (total + stamp duty - withholding tax - VAT when split payment active).
     */
    public function getNetDueProperty(): float
    {
        $due = $this->totalDue - $this->withholdingTaxAmount;

        // Split payment: customer pays net + fund only, VAT goes directly to tax authority
        if ($this->split_payment) {
            $due -= $this->totalVat - $this->fundVatAmount;
        }

        return $due;
    }

    /**
     * Whether the gross total exceeds the stamp duty threshold.
     */
    public function getStampDutyEligibleProperty(): bool
    {
        return $this->totalGross > (float) $this->stamp_duty_threshold;
    }
}
