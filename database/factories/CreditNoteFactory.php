<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\CreditNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CreditNote>
 */
class CreditNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'NC-'.$this->faker->unique()->numberBetween(1, 9999),
            'sequential_number' => $this->faker->numberBetween(1, 999),
            'date' => now(),
            'contact_id' => Contact::factory(),
            'sequence_id' => null,
            'type' => 'credit_note',
            'document_type' => 'TD04',
            'fiscal_year' => now()->year,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'total_net' => 0,
            'total_vat' => 0,
            'total_gross' => 0,
        ];
    }
}
