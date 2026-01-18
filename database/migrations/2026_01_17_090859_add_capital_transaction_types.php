<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Add new enum values to treasury_transactions.type column
            DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM(
                'collection',
                'payment',
                'refund',
                'capital_deposit',
                'partner_drawing',
                'partner_loan_receipt',
                'partner_loan_repayment',
                'employee_advance',
                'salary_payment',
                'income',
                'expense',
                'discount',
                'profit_allocation',
                'asset_contribution',
                'depreciation_expense'
            ) NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // Remove the new enum values
            DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM(
                'collection',
                'payment',
                'refund',
                'capital_deposit',
                'partner_drawing',
                'partner_loan_receipt',
                'partner_loan_repayment',
                'employee_advance',
                'salary_payment',
                'income',
                'expense',
                'discount'
            ) NOT NULL");
        }
    }
};
