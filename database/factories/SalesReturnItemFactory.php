<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\SalesReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalesReturnItem>
 */
class SalesReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 10);
        $unitPrice = fake()->randomFloat(4, 10, 100);
        $discount = 0;
        $total = ($quantity * $unitPrice) - $discount;

        return [
            'sales_return_id' => SalesReturn::factory(),
            'product_id' => Product::factory(),
            'unit_type' => 'small',
            'quantity' => $quantity,
            'unit_price' => (string)$unitPrice,
            'discount' => (string)$discount,
            'total' => (string)$total,
        ];
    }

    /**
     * Indicate that the item uses large unit.
     */
    public function largeUnit(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_type' => 'large',
        ]);
    }
}
