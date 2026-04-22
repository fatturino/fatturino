<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Contact>
 */
class ContactFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'vat_number' => $this->faker->numerify('###########'),
            'tax_code' => $this->faker->numerify('###########'),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'postal_code' => $this->faker->postcode(),
            'province' => $this->faker->stateAbbr(),
            'country' => 'IT',
            'is_customer' => true,
        ];
    }
}
