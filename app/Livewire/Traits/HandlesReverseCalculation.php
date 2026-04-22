<?php

namespace App\Livewire\Traits;

use App\Enums\VatRate;

/**
 * Reverse calculation (scorporo) logic shared by invoice Create/Edit components.
 *
 * Given a desired net-due amount, computes the imponibile (pre-VAT, pre-fund,
 * pre-stamp) and can apply it back to the invoice lines by scaling unit prices.
 *
 * Requires the host component to expose the same tax/fund/withholding/stamp
 * properties consumed by {@see CalculatesInvoiceTotals}.
 */
trait HandlesReverseCalculation
{
    public bool $reverseCalcModal = false;

    public string $reverseCalcDesiredNet = '';

    public ?string $reverseCalcVatRate = null;

    /**
     * Open the reverse calculation modal with sensible defaults.
     */
    public function openReverseCalcModal(): void
    {
        if (empty($this->lines)) {
            $this->error(__('app.invoices.reverse_calc_no_lines'));

            return;
        }

        // Default VAT rate from first line
        $this->reverseCalcVatRate = $this->lines[0]['vat_rate'] ?? VatRate::R22->value;
        $this->reverseCalcDesiredNet = '';
        $this->reverseCalcModal = true;
    }

    /**
     * Reverse-calculated imponibile from the desired net amount.
     */
    public function getReverseCalcNetProperty(): float
    {
        $desired = (float) $this->reverseCalcDesiredNet;
        if ($desired <= 0) {
            return 0.0;
        }

        // Build the multiplier: net × multiplier + stamp = netDue
        $vatPercent = 0;
        if ($this->reverseCalcVatRate) {
            $reverseVatRate = VatRate::tryFrom($this->reverseCalcVatRate);
            $vatPercent = $reverseVatRate ? $reverseVatRate->percent() / 100 : 0;
        }

        $fundPercent = ($this->fund_enabled && $this->fund_percent)
            ? (float) $this->fund_percent / 100
            : 0;

        $fundVatPercent = 0;
        if ($fundPercent > 0 && $this->fund_vat_rate) {
            $fvRate = VatRate::tryFrom($this->fund_vat_rate);
            $fundVatPercent = $fvRate ? $fvRate->percent() / 100 : 0;
        }

        $withholdingPercent = ($this->withholding_tax_enabled && $this->withholding_tax_percent)
            ? (float) $this->withholding_tax_percent / 100
            : 0;

        $stamp = $this->stamp_duty_applied ? 2.00 : 0;

        // netDue = net × (1 + vat + fund + fund×fundVat - withholding) + stamp
        $multiplier = 1 + $vatPercent + $fundPercent + ($fundPercent * $fundVatPercent) - $withholdingPercent;

        if ($multiplier <= 0) {
            return 0.0;
        }

        return ($desired - $stamp) / $multiplier;
    }

    /**
     * Apply the reverse calculation: scale all line unit prices proportionally.
     */
    public function applyReverseCalculation(): void
    {
        $targetNet = $this->reverseCalcNet;
        if ($targetNet <= 0) {
            return;
        }

        $currentNet = $this->totalNet;

        if ($currentNet > 0) {
            // Scale existing line prices proportionally
            $scale = $targetNet / $currentNet;
            foreach ($this->lines as $index => $line) {
                $this->lines[$index]['unit_price'] = round((float) $line['unit_price'] * $scale, 2);
            }
        } elseif (count($this->lines) === 1) {
            // Single empty line: set price directly (quantity defaults to 1)
            $qty = (float) $this->lines[0]['quantity'] ?: 1;
            $this->lines[0]['unit_price'] = round($targetNet / $qty, 2);
        }

        // Trigger stamp duty auto-toggle if enabled
        if ($this->auto_stamp_duty) {
            $this->stamp_duty_applied = $this->stampDutyEligible;
        }

        $this->reverseCalcModal = false;
    }
}
