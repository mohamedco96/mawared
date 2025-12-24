<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Partner>
 */
class PartnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'phone' => fake()->phoneNumber(),
            'type' => 'customer',
            'gov_id' => fake()->city(),
            'region' => fake()->streetName(),
            'is_banned' => false,
            'current_balance' => '0.00',
        ];
    }

    /**
     * Indicate that the partner is a customer.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'customer',
        ]);
    }

    /**
     * Indicate that the partner is a supplier.
     */
    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'supplier',
        ]);
    }
}
