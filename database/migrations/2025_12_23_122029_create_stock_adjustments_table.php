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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignUlid('product_id')->constrained('products')->onDelete('restrict');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->enum('type', ['damage', 'opening', 'gift', 'other'])->index();
            $table->integer('quantity')->comment('Positive for adjustment_in, negative for adjustment_out. Always in base unit');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('status');
            $table->index(['warehouse_id', 'product_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
