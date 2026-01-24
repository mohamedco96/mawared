<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'treasury_id' => Treasury::factory(),
            'expense_date' => now(),
            'created_by' => User::factory(),
            'expense_category_id' => null,
            'beneficiary_name' => null,
            'attachment' => null,
        ];
    }

    public function withCategory(?ExpenseCategory $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_category_id' => $category?->id ?? ExpenseCategory::factory(),
        ]);
    }

    public function withBeneficiary(?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'beneficiary_name' => $name ?? $this->faker->name(),
        ]);
    }

    public function withAttachment(?string $path = null): static
    {
        return $this->state(fn (array $attributes) => [
            'attachment' => $path ?? 'expenses/' . $this->faker->uuid() . '.pdf',
        ]);
    }

    public function withFullDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_category_id' => ExpenseCategory::factory(),
            'beneficiary_name' => $this->faker->name(),
            'attachment' => 'expenses/' . $this->faker->uuid() . '.pdf',
        ]);
    }
}
