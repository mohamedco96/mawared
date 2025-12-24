<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PurchaseInvoice>
 */
class PurchaseInvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_number' => 'PINV-' . fake()->unique()->numberBetween(10000, 99999),
            'warehouse_id' => Warehouse::factory(),
            'partner_id' => Partner::factory()->supplier(),
            'status' => 'draft',
            'payment_method' => 'cash',
            'subtotal' => '0.00',
            'discount' => '0.00',
            'total' => '0.00',
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the invoice is posted.
     */
    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'posted',
        ]);
    }

    /**
     * Indicate the payment method is credit.
     */
    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'credit',
        ]);
    }
}
