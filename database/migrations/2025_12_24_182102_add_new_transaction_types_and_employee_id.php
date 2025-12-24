<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Extend type enum
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('income', 'expense', 'collection', 'payment', 'refund', 'capital_deposit', 'partner_drawing', 'employee_advance') NOT NULL");

        // Add employee_id
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->foreignUlid('employee_id')->nullable()->after('partner_id')->constrained('users')->onDelete('restrict');
            $table->index('employee_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('treasury_transactions', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });
        DB::statement("ALTER TABLE treasury_transactions MODIFY COLUMN type ENUM('income', 'expense', 'collection', 'payment', 'refund') NOT NULL");
    }
};
