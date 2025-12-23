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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignUlid('product_id')->constrained('products')->onDelete('restrict');
            $table->enum('type', ['sale', 'purchase', 'adjustment_in', 'adjustment_out', 'transfer'])->index();
            $table->integer('quantity')->comment('Positive for in, negative for out. Always in base unit (small_unit)');
            $table->decimal('cost_at_time', 18, 4)->comment('Product cost at the time of movement');
            
            // Polymorphic reference to source document
            $table->string('reference_type')->index();
            $table->ulid('reference_id')->index();
            
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['warehouse_id', 'product_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
