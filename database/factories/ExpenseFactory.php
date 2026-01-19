<?php

namespace Database\Factories;

use App\Models\Expense;
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
        ];
    }
}
