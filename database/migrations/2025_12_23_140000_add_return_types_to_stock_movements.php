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
        // Add 'sale_return' and 'purchase_return' to stock_movements type enum
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('sale', 'purchase', 'adjustment_in', 'adjustment_out', 'transfer', 'sale_return', 'purchase_return') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'sale_return' and 'purchase_return' from enum
        DB::statement("ALTER TABLE stock_movements MODIFY COLUMN type ENUM('sale', 'purchase', 'adjustment_in', 'adjustment_out', 'transfer') NOT NULL");
    }
};
