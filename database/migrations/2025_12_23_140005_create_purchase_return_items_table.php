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
        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('purchase_return_id')->constrained('purchase_returns')->onDelete('cascade');
            $table->foreignUlid('product_id')->constrained('products')->onDelete('restrict');
            $table->enum('unit_type', ['small', 'large'])->default('small');
            $table->integer('quantity')->comment('Quantity in selected unit (small or large)');
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('total', 18, 4);
            $table->timestamps();

            // Indexes
            $table->index('purchase_return_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
    }
};
