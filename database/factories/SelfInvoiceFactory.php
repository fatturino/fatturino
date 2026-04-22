<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SelfInvoice>
 */
class SelfInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'AF-' . $this->faker->unique()->numberBetween(1, 9999),
            'sequential_number' => $this->faker->numberBetween(1, 999),
            'date' => now(),
            'contact_id' => Contact::factory(),
            'sequence_id' => null,
            'type' => 'self_invoice',
            'document_type' => 'TD17',
            'fiscal_year' => now()->year,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
            'related_invoice_number' => $this->faker->numerify('##/####'),
            'related_invoice_date' => now()->subMonth(),
        ];
    }

    public function td17(): static
    {
        return $this->state(['document_type' => 'TD17']);
    }

    public function td18(): static
    {
        return $this->state(['document_type' => 'TD18']);
    }

    public function td19(): static
    {
        return $this->state(['document_type' => 'TD19']);
    }

    public function td28(): static
    {
        return $this->state(['document_type' => 'TD28']);
    }
}
