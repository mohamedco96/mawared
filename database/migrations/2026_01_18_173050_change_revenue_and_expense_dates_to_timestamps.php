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
        // Change revenue_date to timestamp
        Schema::table('revenues', function (Blueprint $table) {
            $table->timestamp('revenue_date')->change();
        });

        // Change expense_date to timestamp
        Schema::table('expenses', function (Blueprint $table) {
            $table->timestamp('expense_date')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert revenue_date to date
        Schema::table('revenues', function (Blueprint $table) {
            $table->date('revenue_date')->change();
        });

        // Revert expense_date to date
        Schema::table('expenses', function (Blueprint $table) {
            $table->date('expense_date')->change();
        });
    }
};
