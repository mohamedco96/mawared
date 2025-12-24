<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseInvoiceItem>
 */
class PurchaseInvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 100);
        $unitCost = fake()->randomFloat(4, 10, 100);
        $discount = 0;
        $total = ($quantity * $unitCost) - $discount;

        return [
            'purchase_invoice_id' => PurchaseInvoice::factory(),
            'product_id' => Product::factory(),
            'unit_type' => 'small',
            'quantity' => $quantity,
            'unit_cost' => (string)$unitCost,
            'discount' => (string)$discount,
            'total' => (string)$total,
            'new_selling_price' => null,
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

    /**
     * Indicate that the item should update selling price.
     */
    public function withNewPrice(string $newPrice): static
    {
        return $this->state(fn (array $attributes) => [
            'new_selling_price' => $newPrice,
        ]);
    }
}
