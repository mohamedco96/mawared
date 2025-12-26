<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add composite indexes for stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['warehouse_id', 'product_id'], 'idx_warehouse_product');
            $table->index(['product_id', 'created_at'], 'idx_product_date');
        });

        // Add composite index for treasury_transactions
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->index(['treasury_id', 'created_at'], 'idx_treasury_date');
        });

        // Add composite index for sales_invoices
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->index(['partner_id', 'status'], 'idx_partner_status');
        });

        // Add composite index for purchase_invoices
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->index(['partner_id', 'status'], 'idx_partner_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove indexes from stock_movements
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_warehouse_product');
            $table->dropIndex('idx_product_date');
        });

        // Remove index from treasury_transactions
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_treasury_date');
        });

        // Remove index from sales_invoices
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_partner_status');
        });

        // Remove index from purchase_invoices
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_partner_status');
        });
    }
};
