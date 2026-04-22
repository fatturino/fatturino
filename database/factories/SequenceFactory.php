<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sequence>
 */
class SequenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->word() . ' ' . $this->faker->year(),
            'type' => 'electronic_invoice',
            'pattern' => '{SEQ}',
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(['is_system' => true]);
    }

    public function forType(string $type): static
    {
        return $this->state(['type' => $type]);
    }

    public function withYear(): static
    {
        return $this->state(['pattern' => '{SEQ}-{ANNO}']);
    }

    public function purchase(): static
    {
        return $this->state(['type' => 'purchase']);
    }

    public function selfInvoice(): static
    {
        return $this->state(['type' => 'self_invoice']);
    }

    public function proforma(): static
    {
        return $this->state(['type' => 'proforma']);
    }
}
