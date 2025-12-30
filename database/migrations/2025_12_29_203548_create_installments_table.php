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
        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->char('sales_invoice_id', 26); // ULID from sales_invoices
            $table->foreign('sales_invoice_id')->references('id')->on('sales_invoices')->cascadeOnDelete();
            $table->integer('installment_number'); // 1, 2, 3, etc.
            $table->decimal('amount', 18, 4); // Match existing precision (18,4)
            $table->date('due_date'); // When payment is due
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->decimal('paid_amount', 18, 4)->default(0); // Amount paid toward this installment
            $table->char('invoice_payment_id', 26)->nullable(); // ULID from invoice_payments
            $table->foreign('invoice_payment_id')->references('id')->on('invoice_payments')->nullOnDelete();

            // Audit fields
            $table->timestamp('paid_at')->nullable(); // When fully paid
            $table->char('paid_by', 26)->nullable(); // ULID from users
            $table->foreign('paid_by')->references('id')->on('users');

            $table->text('notes')->nullable();
            $table->timestamps();

            // Optimized indexes for efficient queries
            $table->index(['sales_invoice_id', 'status', 'due_date']); // For FIFO payment application
            $table->index(['status', 'due_date']); // For overdue checks
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installments');
    }
};
