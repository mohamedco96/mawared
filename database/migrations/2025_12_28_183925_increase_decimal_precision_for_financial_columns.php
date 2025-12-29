<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * CRITICAL FIX: Increase decimal precision to prevent data loss
     * for fractional values (e.g., 0.001 becoming 0.0)
     *
     * Changes DECIMAL(10,2) to DECIMAL(15,4) for financial columns
     * Uses Laravel Schema builder for database-agnostic SQL (works with MySQL and SQLite)
     */
    public function up(): void
    {
        // Update stock_movements table - cost_at_time column (CRITICAL for avg_cost calculation)
        if (Schema::hasColumn('stock_movements', 'cost_at_time')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->decimal('cost_at_time', 15, 4)->change();
            });
        }

        // Update invoice items tables
        if (Schema::hasColumn('sales_invoice_items', 'unit_price')) {
            Schema::table('sales_invoice_items', function (Blueprint $table) {
                $table->decimal('unit_price', 15, 4)->change();
            });
        }
        if (Schema::hasColumn('sales_invoice_items', 'total')) {
            Schema::table('sales_invoice_items', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->change();
            });
        }

        if (Schema::hasColumn('purchase_invoice_items', 'unit_cost')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->decimal('unit_cost', 15, 4)->change();
            });
        }
        if (Schema::hasColumn('purchase_invoice_items', 'total')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->change();
            });
        }
        if (Schema::hasColumn('purchase_invoice_items', 'new_selling_price')) {
            Schema::table('purchase_invoice_items', function (Blueprint $table) {
                $table->decimal('new_selling_price', 15, 4)->nullable()->change();
            });
        }

        // Update return items tables
        if (Schema::hasColumn('sales_return_items', 'unit_price')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->decimal('unit_price', 15, 4)->change();
            });
        }
        if (Schema::hasColumn('sales_return_items', 'total')) {
            Schema::table('sales_return_items', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->change();
            });
        }

        if (Schema::hasColumn('purchase_return_items', 'unit_cost')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                $table->decimal('unit_cost', 15, 4)->change();
            });
        }
        if (Schema::hasColumn('purchase_return_items', 'total')) {
            Schema::table('purchase_return_items', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->change();
            });
        }

        // Update invoice tables
        if (Schema::hasColumn('sales_invoices', 'subtotal')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('sales_invoices', 'discount')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->decimal('discount', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('sales_invoices', 'total')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('sales_invoices', 'paid_amount')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->decimal('paid_amount', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('sales_invoices', 'remaining_amount')) {
            Schema::table('sales_invoices', function (Blueprint $table) {
                $table->decimal('remaining_amount', 15, 4)->default(0)->change();
            });
        }

        if (Schema::hasColumn('purchase_invoices', 'subtotal')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('purchase_invoices', 'discount')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->decimal('discount', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('purchase_invoices', 'total')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('purchase_invoices', 'paid_amount')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->decimal('paid_amount', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('purchase_invoices', 'remaining_amount')) {
            Schema::table('purchase_invoices', function (Blueprint $table) {
                $table->decimal('remaining_amount', 15, 4)->default(0)->change();
            });
        }

        // Update returns tables
        if (Schema::hasColumn('sales_returns', 'subtotal')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('sales_returns', 'discount')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->decimal('discount', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('sales_returns', 'total')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->default(0)->change();
            });
        }

        if (Schema::hasColumn('purchase_returns', 'subtotal')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('purchase_returns', 'discount')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                $table->decimal('discount', 15, 4)->default(0)->change();
            });
        }
        if (Schema::hasColumn('purchase_returns', 'total')) {
            Schema::table('purchase_returns', function (Blueprint $table) {
                $table->decimal('total', 15, 4)->default(0)->change();
            });
        }

        // Update treasury and payment tables
        if (Schema::hasColumn('treasury_transactions', 'amount')) {
            Schema::table('treasury_transactions', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->change();
            });
        }

        if (Schema::hasColumn('invoice_payments', 'amount')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->change();
            });
        }
        if (Schema::hasColumn('invoice_payments', 'discount')) {
            Schema::table('invoice_payments', function (Blueprint $table) {
                $table->decimal('discount', 15, 4)->default(0)->change();
            });
        }

        // Update expenses and revenues
        if (Schema::hasColumn('expenses', 'amount')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->change();
            });
        }

        if (Schema::hasTable('revenues') && Schema::hasColumn('revenues', 'amount')) {
            Schema::table('revenues', function (Blueprint $table) {
                $table->decimal('amount', 15, 4)->change();
            });
        }

        // Update partners balance
        if (Schema::hasColumn('partners', 'current_balance')) {
            Schema::table('partners', function (Blueprint $table) {
                $table->decimal('current_balance', 15, 4)->default(0)->change();
            });
        }

        // Update products avg_cost (CRITICAL for small decimal values like 0.001)
        if (Schema::hasColumn('products', 'avg_cost')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('avg_cost', 15, 4)->default(0)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: Reverting precision may cause data loss if fractional values exist
        // Only revert if absolutely necessary

        if (Schema::hasColumn('stock_movements', 'cost_at_time')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->decimal('cost_at_time', 10, 2)->change();
            });
        }

        // We won't revert all columns to save space, but the pattern is:
        // Schema::table('table_name', function (Blueprint $table) {
        //     $table->decimal('column_name', 10, 2)->change();
        // });
    }
};
