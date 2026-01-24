<?php

namespace Database\Factories;

use App\Enums\ExpenseCategoryType;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'type' => $this->faker->randomElement(ExpenseCategoryType::cases()),
            'is_active' => true,
        ];
    }

    public function operational(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ExpenseCategoryType::OPERATIONAL,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ExpenseCategoryType::ADMIN,
        ]);
    }

    public function marketing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ExpenseCategoryType::MARKETING,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
