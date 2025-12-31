<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        $pricingType = $this->faker->randomElement(['retail', 'wholesale']);
        $status = $this->faker->randomElement(['draft', 'sent', 'accepted', 'rejected']);

        // 70% of quotations have a partner, 30% are guests
        $hasPartner = $this->faker->boolean(70);

        $discountType = $this->faker->randomElement(['percentage', 'fixed']);
        $discountValue = $this->faker->randomElement([0, 5, 10, 15, 20, 50, 100]);

        return [
            'partner_id' => $hasPartner ? Partner::factory()->state(['type' => 'customer']) : null,
            'guest_name' => !$hasPartner ? $this->faker->name() : null,
            'guest_phone' => !$hasPartner ? $this->faker->numerify('05########') : null,
            'pricing_type' => $pricingType,
            'status' => $status,
            'public_token' => Str::random(32),
            'valid_until' => $this->faker->optional(0.7)->dateTimeBetween('now', '+60 days'),
            'subtotal' => 0, // Will be calculated based on items
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount' => 0, // Will be calculated
            'total' => 0, // Will be calculated
            'notes' => $this->faker->optional(0.5)->sentence(),
            'internal_notes' => $this->faker->optional(0.3)->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sent',
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'accepted',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }

    public function withPartner(): static
    {
        return $this->state(fn (array $attributes) => [
            'partner_id' => Partner::factory()->state(['type' => 'customer']),
            'guest_name' => null,
            'guest_phone' => null,
        ]);
    }

    public function withGuest(): static
    {
        return $this->state(fn (array $attributes) => [
            'partner_id' => null,
            'guest_name' => $this->faker->name(),
            'guest_phone' => $this->faker->numerify('05########'),
        ]);
    }
}
