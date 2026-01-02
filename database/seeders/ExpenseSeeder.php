<?php

namespace Database\Seeders;

use App\Models\Expense;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Database\Seeder;

class ExpenseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $treasury = Treasury::where('name', 'الخزينة الرئيسية')->first();
        $user = User::first();

        $expenses = [
            [
                'title' => 'إيجار المكتب - يناير 2025',
                'description' => 'إيجار شهري للمكتب الرئيسي',
                'amount' => 5000.00,
                'treasury_id' => $treasury->id,
                'expense_date' => now()->subDays(10),
                'created_by' => $user->id,
            ],
            [
                'title' => 'فواتير كهرباء',
                'description' => 'فاتورة الكهرباء لشهر ديسمبر',
                'amount' => 1200.00,
                'treasury_id' => $treasury->id,
                'expense_date' => now()->subDays(5),
                'created_by' => $user->id,
            ],
            [
                'title' => 'مرتبات الموظفين',
                'description' => 'مرتبات شهر ديسمبر 2024',
                'amount' => 25000.00,
                'treasury_id' => $treasury->id,
                'expense_date' => now()->subDays(2),
                'created_by' => $user->id,
            ],
            [
                'title' => 'صيانة معدات',
                'description' => 'صيانة دورية للمعدات',
                'amount' => 800.00,
                'treasury_id' => $treasury->id,
                'expense_date' => now()->subDay(),
                'created_by' => $user->id,
            ],
        ];

        foreach ($expenses as $expense) {
            $expenseModel = Expense::create($expense);
            // Explicitly post to treasury (observer disabled to prevent duplicates)
            app(\App\Services\TreasuryService::class)->postExpense($expenseModel);
        }
    }
}
