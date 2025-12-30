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
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->boolean('has_installment_plan')->default(false)->after('remaining_amount');
            $table->integer('installment_months')->nullable()->after('has_installment_plan');
            $table->date('installment_start_date')->nullable()->after('installment_months');
            $table->text('installment_notes')->nullable()->after('installment_start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn([
                'has_installment_plan',
                'installment_months',
                'installment_start_date',
                'installment_notes',
            ]);
        });
    }
};
