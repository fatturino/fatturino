<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProformaInvoice>
 */
class ProformaInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'PRO-' . $this->faker->unique()->numberBetween(1, 9999),
            'sequential_number' => $this->faker->numberBetween(1, 999),
            'date' => now(),
            'contact_id' => Contact::factory(),
            'sequence_id' => null,
            'type' => 'proforma',
            'fiscal_year' => now()->year,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
            'withholding_tax_enabled' => false,
            'withholding_tax_percent' => '20.00',
            'fund_enabled' => false,
            'fund_has_deduction' => false,
            'stamp_duty_applied' => false,
            'stamp_duty_amount' => 0,
        ];
    }

    public function converted(): static
    {
        return $this->state(['status' => 'converted']);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => 'cancelled']);
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent']);
    }
}
