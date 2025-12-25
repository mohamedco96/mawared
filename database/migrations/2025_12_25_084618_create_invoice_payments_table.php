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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Polymorphic relationship to invoice/return
            $table->string('payable_type')->index();
            $table->ulid('payable_id')->index();

            $table->decimal('amount', 18, 4)->comment('Amount paid in this payment');
            $table->decimal('discount', 18, 4)->default(0)->comment('Discount given with this payment');
            $table->date('payment_date');
            $table->text('notes')->nullable();

            // Link to treasury transaction (the actual cash movement)
            $table->foreignUlid('treasury_transaction_id')->nullable()
                ->constrained('treasury_transactions')->onDelete('restrict');

            // Link to partner
            $table->foreignUlid('partner_id')->constrained('partners')->onDelete('restrict');

            $table->foreignUlid('created_by')->nullable()->constrained('users')->onDelete('set null');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['payable_type', 'payable_id']);
            $table->index(['partner_id']);
            $table->index(['payment_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
