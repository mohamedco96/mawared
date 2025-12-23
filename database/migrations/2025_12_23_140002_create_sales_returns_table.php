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
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('return_number')->unique()->index();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignUlid('partner_id')->constrained('partners')->onDelete('restrict');
            $table->enum('status', ['draft', 'posted'])->default('draft')->index();
            $table->enum('payment_method', ['cash', 'credit'])->default('cash');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Additional indexes (status, partner_id, warehouse_id already indexed above)
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
