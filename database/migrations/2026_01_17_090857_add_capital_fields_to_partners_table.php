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
        Schema::table('partners', function (Blueprint $table) {
            $table->decimal('current_capital', 18, 4)->default(0)->after('current_balance');
            $table->decimal('equity_percentage', 8, 4)->nullable()->after('current_capital');
            $table->boolean('is_manager')->default(false)->after('equity_percentage');
            $table->decimal('monthly_salary', 18, 4)->nullable()->after('is_manager');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn(['current_capital', 'equity_percentage', 'is_manager', 'monthly_salary']);
        });
    }
};
