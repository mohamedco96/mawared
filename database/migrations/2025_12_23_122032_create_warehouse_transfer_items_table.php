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
        Schema::create('warehouse_transfer_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('warehouse_transfer_id')->constrained('warehouse_transfers')->onDelete('cascade');
            $table->foreignUlid('product_id')->constrained('products')->onDelete('restrict');
            $table->integer('quantity')->comment('Quantity in base unit (small_unit)');
            $table->timestamps();
            
            $table->index(['warehouse_transfer_id']);
            $table->index(['product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfer_items');
    }
};
