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
        // Add 'refund' to treasury_transactions type enum
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('income', 'expense', 'collection', 'payment', 'refund') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'refund' from enum
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('income', 'expense', 'collection', 'payment') NOT NULL");
    }
};
