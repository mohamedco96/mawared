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
        Schema::create('warehouse_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('transfer_number')->unique();
            $table->foreignUlid('from_warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignUlid('to_warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['from_warehouse_id']);
            $table->index(['to_warehouse_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_transfers');
    }
};
