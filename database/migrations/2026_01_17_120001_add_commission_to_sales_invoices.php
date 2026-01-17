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
            $table->foreignUlid('sales_person_id')->nullable()
                ->after('partner_id')
                ->constrained('users')
                ->onDelete('set null')
                ->comment('Salesperson who made this sale');

            $table->decimal('commission_rate', 5, 2)->nullable()
                ->after('cost_total')
                ->comment('Commission rate as percentage (e.g., 5.00 for 5%)');

            $table->decimal('commission_amount', 18, 4)->default(0)
                ->after('commission_rate')
                ->comment('Calculated commission amount based on rate');

            $table->boolean('commission_paid')->default(false)
                ->after('commission_amount')
                ->comment('Whether commission has been paid to salesperson');

            $table->index('sales_person_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table) {
            $table->dropForeign(['sales_person_id']);
            $table->dropIndex(['sales_person_id']);
            $table->dropColumn([
                'sales_person_id',
                'commission_rate',
                'commission_amount',
                'commission_paid',
            ]);
        });
    }
};
