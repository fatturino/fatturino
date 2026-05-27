<?php

namespace Database\Factories;

use App\Enums\VatRate;
use App\Models\FiscalDocument;
use App\Models\FiscalDocumentLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalDocumentLine>
 */
class InvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fiscal_document_id' => FiscalDocument::factory(),
            'description' => $this->faker->sentence(),
            'quantity' => '1.00',
            'unit_of_measure' => null,
            'unit_price' => 10000, // 100.00 EUR in cents
            'discount_percent' => null,
            'discount_amount' => null,
            'total' => 10000, // 100.00 EUR in cents
            'vat_rate' => VatRate::R22->value,
            'product_id' => null,
        ];
    }

    public function forInvoice(int $invoiceId): static
    {
        return $this->state(['fiscal_document_id' => $invoiceId]);
    }

    public function withDiscount(float $discountPercent): static
    {
        return $this->state(['discount_percent' => $discountPercent]);
    }
}
