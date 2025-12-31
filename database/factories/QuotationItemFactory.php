<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    public function definition(): array
    {
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();
        $unitType = $this->faker->randomElement(['small', 'large']);
        $quantity = $this->faker->numberBetween(1, 20);

        // Get unit price based on quotation pricing type and unit type
        $quotation = Quotation::inRandomOrder()->first();
        $pricingType = $quotation ? $quotation->pricing_type : 'retail';

        if ($unitType === 'small') {
            $unitPrice = $pricingType === 'retail' ? $product->retail_price : $product->wholesale_price;
            $unitName = $product->smallUnit->name ?? 'قطعة';
        } else {
            $unitPrice = $pricingType === 'retail' ? $product->large_retail_price : $product->large_wholesale_price;
            $unitName = $product->largeUnit->name ?? 'كرتونة';
        }

        $discount = $this->faker->randomElement([0, 0, 0, 5, 10, 15, 20]); // Most items have no discount
        $total = ($quantity * $unitPrice) - $discount;

        return [
            'quotation_id' => Quotation::factory(),
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_type' => $unitType,
            'unit_name' => $unitName,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'total' => $total,
            'notes' => $this->faker->optional(0.2)->sentence(),
        ];
    }
}
