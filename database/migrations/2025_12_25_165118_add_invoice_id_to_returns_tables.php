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
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->foreignUlid('sales_invoice_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('sales_invoices')
                ->onDelete('restrict');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->foreignUlid('purchase_invoice_id')
                ->nullable()
                ->after('partner_id')
                ->constrained('purchase_invoices')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_returns', function (Blueprint $table) {
            $table->dropForeign(['sales_invoice_id']);
            $table->dropColumn('sales_invoice_id');
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            $table->dropForeign(['purchase_invoice_id']);
            $table->dropColumn('purchase_invoice_id');
        });
    }
};
