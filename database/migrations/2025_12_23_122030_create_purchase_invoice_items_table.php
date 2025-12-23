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
        Schema::create('purchase_invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_invoice_id')->constrained('purchase_invoices')->onDelete('cascade');
            $table->foreignUlid('product_id')->constrained('products')->onDelete('restrict');
            $table->enum('unit_type', ['small', 'large'])->default('small');
            $table->integer('quantity')->comment('Quantity in selected unit (small or large)');
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('total', 18, 4);
            $table->decimal('new_selling_price', 18, 4)->nullable()->comment('If set, updates product selling price');
            $table->timestamps();
            
            $table->index(['purchase_invoice_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoice_items');
    }
};
