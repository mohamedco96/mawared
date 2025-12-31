<?php

namespace Database\Factories;

use App\Models\Installment;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InstallmentFactory extends Factory
{
    protected $model = Installment::class;

    public function definition(): array
    {
        $amount = $this->faker->randomFloat(4, 100, 5000);
        $status = $this->faker->randomElement(['pending', 'paid', 'overdue']);
        $paidAmount = $status === 'paid' ? $amount : ($status === 'overdue' && $this->faker->boolean(30) ? $this->faker->randomFloat(4, 0, $amount) : 0);

        return [
            'sales_invoice_id' => SalesInvoice::factory(),
            'installment_number' => $this->faker->numberBetween(1, 12),
            'amount' => $amount,
            'due_date' => $this->faker->dateTimeBetween('-30 days', '+90 days'),
            'status' => $status,
            'paid_amount' => $paidAmount,
            'invoice_payment_id' => null, // Will be set when payment is created
            'paid_at' => $status === 'paid' ? $this->faker->dateTimeBetween('-60 days', 'now') : null,
            'paid_by' => $status === 'paid' ? User::factory() : null,
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_amount' => 0,
            'paid_at' => null,
            'paid_by' => null,
            'due_date' => $this->faker->dateTimeBetween('now', '+90 days'),
        ]);
    }

    public function paid(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'paid',
                'paid_amount' => $attributes['amount'],
                'paid_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
                'paid_by' => User::factory(),
            ];
        });
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'overdue',
            'paid_amount' => 0,
            'paid_at' => null,
            'paid_by' => null,
            'due_date' => $this->faker->dateTimeBetween('-60 days', '-1 day'),
        ]);
    }

    public function partiallyPaid(): static
    {
        return $this->state(function (array $attributes) {
            $partialAmount = $this->faker->randomFloat(4, $attributes['amount'] * 0.1, $attributes['amount'] * 0.9);
            return [
                'status' => 'overdue',
                'paid_amount' => $partialAmount,
                'paid_at' => $this->faker->dateTimeBetween('-60 days', 'now'),
                'paid_by' => User::factory(),
                'due_date' => $this->faker->dateTimeBetween('-30 days', '-1 day'),
            ];
        });
    }
}
