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
        // SQLite doesn't support ALTER COLUMN with ENUM
        // The type field is already a string in SQLite, so no migration needed for testing
        // In production MySQL, this adds the new enum values
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
                    'partner_loan_repayment'
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
            // Check if any records use the new types before rolling back
            $hasNewTypes = DB::table('treasury_transactions')
                ->whereIn('type', ['partner_loan_receipt', 'partner_loan_repayment'])
                ->exists();

            if ($hasNewTypes) {
                throw new \Exception(
                    'Cannot rollback migration: Records with partner loan types exist. ' .
                    'Delete these records first or data will be lost.'
                );
            }

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
                    'employee_advance'
                ) NOT NULL
            ");
        }
    }
};
