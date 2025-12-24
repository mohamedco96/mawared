<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add columns to sales_invoices table
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percentage'])
                  ->default('fixed')
                  ->after('payment_method');
            $table->decimal('discount_value', 18, 4)
                  ->default(0)
                  ->after('discount_type');
            $table->decimal('paid_amount', 18, 4)
                  ->default(0)
                  ->after('total');
            $table->decimal('remaining_amount', 18, 4)
                  ->default(0)
                  ->after('paid_amount');
        });

        // Add columns to purchase_invoices table
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->enum('discount_type', ['fixed', 'percentage'])
                  ->default('fixed')
                  ->after('payment_method');
            $table->decimal('discount_value', 18, 4)
                  ->default(0)
                  ->after('discount_type');
            $table->decimal('paid_amount', 18, 4)
                  ->default(0)
                  ->after('total');
            $table->decimal('remaining_amount', 18, 4)
                  ->default(0)
                  ->after('paid_amount');
        });

        // Migrate existing sales invoices data
        DB::table('sales_invoices')->orderBy('id')->chunk(100, function ($invoices) {
            foreach ($invoices as $invoice) {
                DB::table('sales_invoices')
                    ->where('id', $invoice->id)
                    ->update([
                        'discount_type' => 'fixed',
                        'discount_value' => $invoice->discount ?? 0,
                        'paid_amount' => $invoice->payment_method === 'cash'
                            ? $invoice->total
                            : 0,
                        'remaining_amount' => $invoice->payment_method === 'cash'
                            ? 0
                            : $invoice->total,
                    ]);
            }
        });

        // Migrate existing purchase invoices data
        DB::table('purchase_invoices')->orderBy('id')->chunk(100, function ($invoices) {
            foreach ($invoices as $invoice) {
                DB::table('purchase_invoices')
                    ->where('id', $invoice->id)
                    ->update([
                        'discount_type' => 'fixed',
                        'discount_value' => $invoice->discount ?? 0,
                        'paid_amount' => $invoice->payment_method === 'cash'
                            ? $invoice->total
                            : 0,
                        'remaining_amount' => $invoice->payment_method === 'cash'
                            ? 0
                            : $invoice->total,
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'paid_amount', 'remaining_amount']);
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'paid_amount', 'remaining_amount']);
        });
    }
};
