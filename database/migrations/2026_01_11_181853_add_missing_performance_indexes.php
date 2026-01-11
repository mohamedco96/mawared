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
        // Sales returns foreign key indexes
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->index('partner_id', 'idx_sales_returns_partner');
            $table->index('warehouse_id', 'idx_sales_returns_warehouse');
        });

        // Purchase returns foreign key indexes
        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->index('partner_id', 'idx_purchase_returns_partner');
            $table->index('warehouse_id', 'idx_purchase_returns_warehouse');
        });

        // Partners composite indexes for common filters
        Schema::table('partners', function (Blueprint $table) {
            $table->index(['type', 'deleted_at'], 'idx_partners_type_deleted');
            $table->index(['type', 'is_banned', 'deleted_at'], 'idx_partners_active');
        });

        // Invoice payments indexes
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->index('treasury_transaction_id', 'idx_payments_treasury_txn');
            $table->index('created_by', 'idx_payments_creator');
        });

        // Fixed assets indexes
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->index('treasury_id', 'idx_fixed_assets_treasury');
            $table->index('created_by', 'idx_fixed_assets_creator');
        });

        // Treasury transactions composite index for reports
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->index(['partner_id', 'type', 'created_at'], 'idx_treasury_partner_type');
            $table->index('employee_id', 'idx_treasury_employee');
        });

        // Stock adjustments creator index for audit trails
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->index('created_by', 'idx_stock_adj_creator');
        });

        // Expenses and revenues creator indexes
        Schema::table('expenses', function (Blueprint $table) {
            $table->index('created_by', 'idx_expenses_creator');
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->index('created_by', 'idx_revenues_creator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropIndex('idx_sales_returns_partner');
            $table->dropIndex('idx_sales_returns_warehouse');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropIndex('idx_purchase_returns_partner');
            $table->dropIndex('idx_purchase_returns_warehouse');
        });

        Schema::table('partners', function (Blueprint $table) {
            $table->dropIndex('idx_partners_type_deleted');
            $table->dropIndex('idx_partners_active');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropIndex('idx_payments_treasury_txn');
            $table->dropIndex('idx_payments_creator');
        });

        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropIndex('idx_fixed_assets_treasury');
            $table->dropIndex('idx_fixed_assets_creator');
        });

        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_treasury_partner_type');
            $table->dropIndex('idx_treasury_employee');
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropIndex('idx_stock_adj_creator');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_creator');
        });

        Schema::table('revenues', function (Blueprint $table) {
            $table->dropIndex('idx_revenues_creator');
        });
    }
};
