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
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('barcode')->unique()->nullable();
            $table->string('sku')->unique()->nullable();
            $table->integer('min_stock')->default(0);
            $table->decimal('avg_cost', 18, 4)->default(0);
            
            // Dual-Unit System
            $table->foreignUlid('small_unit_id')->constrained('units')->onDelete('restrict');
            $table->foreignUlid('large_unit_id')->nullable()->constrained('units')->onDelete('restrict');
            $table->integer('factor')->default(1); // Conversion rate: large_unit = small_unit * factor
            
            // Pricing (Small Unit)
            $table->decimal('retail_price', 18, 4)->default(0);
            $table->decimal('wholesale_price', 18, 4)->default(0);
            
            // Pricing (Large Unit)
            $table->decimal('large_retail_price', 18, 4)->nullable();
            $table->decimal('large_wholesale_price', 18, 4)->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['barcode']);
            $table->index(['sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
