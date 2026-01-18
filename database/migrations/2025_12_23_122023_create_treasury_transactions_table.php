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
        Schema::create('treasury_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('treasury_id')->constrained('treasuries')->onDelete('restrict');
            $table->enum('type', [
                'income', 'expense', 'collection', 'payment', 'refund',
                'capital_deposit', 'partner_drawing', 'partner_loan_receipt', 'partner_loan_repayment',
                'employee_advance', 'salary_payment', 'profit_allocation', 'asset_contribution',
                'depreciation_expense', 'commission_payout', 'commission_reversal', 'discount'
            ])->index();
            $table->decimal('amount', 18, 4)->comment('Positive for income/collection, negative for expense/payment');
            $table->text('description');
            
            // Optional: Link to partner for collection/payment
            $table->foreignUlid('partner_id')->nullable()->constrained('partners')->onDelete('restrict');
            
            // Polymorphic reference to source document
            $table->string('reference_type')->nullable()->index();
            $table->ulid('reference_id')->nullable()->index();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['treasury_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['partner_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('treasury_transactions');
    }
};
