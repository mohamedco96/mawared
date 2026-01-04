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
        // Index for profitability queries (sales invoice status and date filtering)
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->index(['status', 'created_at', 'deleted_at'], 'idx_sales_status_date');
        });

        // Composite index for stock movement profit queries
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['reference_type', 'reference_id', 'deleted_at'], 'idx_stock_ref_lookup');
        });

        // Index for slow-moving items filter
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->index(['product_id', 'sales_invoice_id'], 'idx_items_product_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropIndex('idx_sales_status_date');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('idx_stock_ref_lookup');
        });

        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->dropIndex('idx_items_product_invoice');
        });
    }
};
