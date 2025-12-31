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
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->restrictOnDelete();
            $table->string('product_name'); // Snapshot
            $table->enum('unit_type', ['small', 'large'])->default('small');
            $table->string('unit_name', 100); // Snapshot
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 18, 4); // Snapshot price
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('total', 18, 4);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('quotation_id');
            $table->index('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
