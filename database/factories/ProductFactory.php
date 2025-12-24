<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'barcode' => fake()->unique()->ean13(),
            'sku' => strtoupper(fake()->unique()->bothify('???-####')),
            'min_stock' => 10,
            'avg_cost' => '50.00',
            'small_unit_id' => Unit::factory(),
            'large_unit_id' => null,
            'factor' => 1, // Default factor for single-unit products
            'retail_price' => '100.00',
            'wholesale_price' => '90.00',
            'large_retail_price' => null,
            'large_wholesale_price' => null,
        ];
    }

    /**
     * Indicate that the product has a large unit with conversion factor.
     */
    public function withLargeUnit(int $factor = 12): static
    {
        return $this->state(fn (array $attributes) => [
            'large_unit_id' => Unit::factory(),
            'factor' => $factor,
            'large_retail_price' => (string)((float)$attributes['retail_price'] * $factor),
            'large_wholesale_price' => (string)((float)$attributes['wholesale_price'] * $factor),
        ]);
    }
}
