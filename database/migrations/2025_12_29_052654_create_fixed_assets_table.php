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
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('purchase_amount', 19, 4); // Match treasury_transactions precision
            $table->ulid('treasury_id');
            $table->date('purchase_date');
            $table->ulid('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('treasury_id')->references('id')->on('treasuries')->onDelete('restrict');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');

            // Index for performance
            $table->index('purchase_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
