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
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->decimal('wholesale_price', 15, 4)->nullable()->after('new_large_selling_price')->comment('If set, updates product wholesale price');
            $table->decimal('large_wholesale_price', 15, 4)->nullable()->after('wholesale_price')->comment('If set, updates product large wholesale price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['wholesale_price', 'large_wholesale_price']);
        });
    }
};
