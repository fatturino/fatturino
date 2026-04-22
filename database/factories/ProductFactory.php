<?php

namespace Database\Factories;

use App\Enums\VatRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'price' => $this->faker->numberBetween(100, 100000), // cents
            'vat_rate' => VatRate::R22->value,
        ];
    }
}
