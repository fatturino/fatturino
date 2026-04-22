<?php

namespace Database\Factories;

use App\Enums\VatRate;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
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
        return $this->state(['invoice_id' => $invoiceId]);
    }

    public function withDiscount(float $discountPercent): static
    {
        return $this->state(['discount_percent' => $discountPercent]);
    }
}
