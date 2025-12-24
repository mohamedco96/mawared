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
        // In production MySQL, this would add the enum values
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('income', 'expense', 'collection', 'payment', 'refund') NOT NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('income', 'expense', 'collection', 'payment') NOT NULL");
        }
    }
};
