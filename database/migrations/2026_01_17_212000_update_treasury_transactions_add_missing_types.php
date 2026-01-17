<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE treasury_transactions
                MODIFY COLUMN type ENUM(
                    'income',
                    'expense',
                    'collection',
                    'payment',
                    'refund',
                    'capital_deposit',
                    'partner_drawing',
                    'partner_loan_receipt',
                    'partner_loan_repayment',
                    'employee_advance',
                    'salary_payment',
                    'profit_allocation',
                    'asset_contribution',
                    'depreciation_expense',
                    'commission_payout',
                    'commission_reversal'
                ) NOT NULL
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE treasury_transactions
                MODIFY COLUMN type ENUM(
                    'income',
                    'expense',
                    'collection',
                    'payment',
                    'refund',
                    'capital_deposit',
                    'partner_drawing',
                    'employee_advance',
                    'partner_loan_receipt',
                    'partner_loan_repayment',
                    'salary_payment'
                ) NOT NULL
            ");
        }
    }
};
