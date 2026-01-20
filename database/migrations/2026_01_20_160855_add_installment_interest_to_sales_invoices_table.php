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
            $table->decimal('installment_interest_percentage', 5, 2)->default(0)->after('installment_notes');
            $table->decimal('installment_interest_amount', 18, 4)->default(0)->after('installment_interest_percentage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropColumn(['installment_interest_percentage', 'installment_interest_amount']);
        });
    }
};
