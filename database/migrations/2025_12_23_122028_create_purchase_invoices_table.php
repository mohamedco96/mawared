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
        Schema::create('purchase_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('invoice_number')->unique();
            $table->foreignUlid('warehouse_id')->constrained('warehouses')->onDelete('restrict');
            $table->foreignUlid('partner_id')->constrained('partners')->onDelete('restrict');
            $table->enum('status', ['draft', 'posted'])->default('draft');
            $table->enum('payment_method', ['cash', 'credit'])->default('cash');
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('status');
            $table->index(['partner_id']);
            $table->index(['warehouse_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_invoices');
    }
};
