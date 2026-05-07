<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Invoice;
use App\Models\Sequence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'INV-'.$this->faker->unique()->numberBetween(1, 9999),
            'sequential_number' => $this->faker->numberBetween(1, 999),
            'date' => now(),
            'contact_id' => Contact::factory(),
            'sequence_id' => null,
            'type' => 'sales',
            'fiscal_year' => now()->year,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
        ];
    }

    public function sent(): static
    {
        return $this->state([
            'status' => 'sent',
            'sdi_status' => 'sent',
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function withWithholding(float $percent = 20.0): static
    {
        return $this->state([
            'withholding_tax_enabled' => true,
            'withholding_tax_percent' => $percent,
        ]);
    }

    public function withStampDuty(): static
    {
        return $this->state([
            'stamp_duty_applied' => true,
            'stamp_duty_amount' => 200, // 2.00 EUR in cents
        ]);
    }

    public function withSequence(): static
    {
        return $this->state([
            'sequence_id' => Sequence::factory(),
        ]);
    }
}
