<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseInvoice>
 */
class PurchaseInvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'ACQ-'.$this->faker->unique()->numberBetween(1, 9999),
            'sequential_number' => $this->faker->numberBetween(1, 999),
            'date' => now(),
            'contact_id' => Contact::factory(),
            'sequence_id' => null,
            'type' => 'purchase',
            'fiscal_year' => now()->year,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
        ];
    }

    public function fromSdi(string $status = 'delivered'): static
    {
        return $this->state([
            'sdi_status' => $status,
        ]);
    }

    public function paid(): static
    {
        return $this->state([
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function withDueDate(string $dueDate, string $paymentStatus = 'unpaid'): static
    {
        return $this->state([
            'due_date' => $dueDate,
            'payment_status' => $paymentStatus,
        ]);
    }
}
