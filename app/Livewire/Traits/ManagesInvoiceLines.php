<?php

namespace App\Livewire\Traits;

use App\Enums\VatRate;

/**
 * Shared invoice line management for Livewire Create/Edit components.
 *
 * Encapsulates the in-memory `$lines` array lifecycle (add/remove),
 * the discounted-total calculation used by totals and persistence,
 * and the payload builder used when persisting `InvoiceLine` records.
 */
trait ManagesInvoiceLines
{
    public array $lines = [];

    public function addLine(): void
    {
        $this->lines[] = [
            'description' => '',
            'quantity' => 1,
            'unit_of_measure' => '',
            'unit_price' => 0,
            'discount_percent' => null,
            'vat_rate' => VatRate::R22->value,
            'total' => 0,
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    /**
     * Calculate the discounted line total (unit × qty, then apply discount percent).
     */
    protected function lineDiscountedTotal(array $line): float
    {
        $gross = (float) $line['quantity'] * (float) $line['unit_price'];
        $discountPercent = isset($line['discount_percent']) && $line['discount_percent'] !== null
            ? (float) $line['discount_percent']
            : 0.0;

        return $discountPercent > 0 ? $gross * (1 - $discountPercent / 100) : $gross;
    }

    /**
     * Build the persistence payload for a single line (amounts stored in cents).
     *
     * Used by both Create::save() and Edit::save() to avoid duplicated
     * cent-conversion and discount-amount computation.
     */
    protected function buildLinePayload(array $line): array
    {
        $lineDiscountedTotal = $this->lineDiscountedTotal($line);
        $lineGross = (float) $line['quantity'] * (float) $line['unit_price'];

        $discountPercent = isset($line['discount_percent']) && $line['discount_percent'] !== null
            ? (float) $line['discount_percent']
            : null;

        $discountAmount = ($discountPercent !== null && $discountPercent > 0)
            ? (int) round(($lineGross - $lineDiscountedTotal) * 100)
            : null;

        return [
            'description' => $line['description'],
            'quantity' => $line['quantity'],
            'unit_of_measure' => ($line['unit_of_measure'] ?? null) ?: null,
            'unit_price' => (int) round((float) $line['unit_price'] * 100),
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'vat_rate' => $line['vat_rate'],
            'total' => (int) round($lineDiscountedTotal * 100),
        ];
    }
}
