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
        Schema::create('quotations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('quotation_number')->unique();
            $table->foreignUlid('partner_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_phone', 20)->nullable();
            $table->enum('pricing_type', ['retail', 'wholesale', 'manual'])->default('retail');
            $table->enum('status', ['draft', 'sent', 'accepted', 'converted', 'rejected', 'expired'])->default('draft');
            $table->string('public_token', 32)->unique();
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 18, 4)->nullable()->default(0);
            $table->decimal('discount', 18, 4)->default(0);
            $table->decimal('total', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignUlid('converted_invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $table->foreignUlid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('quotation_number');
            $table->index('public_token');
            $table->index('partner_id');
            $table->index('status');
            $table->index('valid_until');
            $table->index('converted_invoice_id');
            $table->index('created_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
