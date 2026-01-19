<?php

namespace Database\Factories;

use App\Models\FixedAsset;
use App\Models\Partner;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FixedAsset>
 */
class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'purchase_amount' => $this->faker->randomFloat(4, 1000, 50000),
            'treasury_id' => Treasury::factory(),
            'purchase_date' => $this->faker->date(),
            'funding_method' => 'cash',
            'supplier_name' => $this->faker->company(),
            'status' => 'active',
            'created_by' => User::factory(),
            'useful_life_years' => $this->faker->numberBetween(1, 10),
            'salvage_value' => $this->faker->randomFloat(4, 0, 500),
            'accumulated_depreciation' => 0,
            'last_depreciation_date' => null,
            'depreciation_method' => 'straight_line',
        ];
    }
}
