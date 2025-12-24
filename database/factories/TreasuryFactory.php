<?php

namespace Database\Factories;

use App\Models\Treasury;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Treasury>
 */
class TreasuryFactory extends Factory
{
    protected $model = Treasury::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Treasury',
            'type' => 'cash',
            'description' => fake()->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the treasury is a bank account.
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'bank',
        ]);
    }
}
