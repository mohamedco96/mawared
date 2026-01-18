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
        Schema::table('equity_periods', function (Blueprint $table) {
            // Change start_date from date to timestamp
            $table->timestamp('start_date')->change();

            // Change end_date from date to timestamp (nullable)
            $table->timestamp('end_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equity_periods', function (Blueprint $table) {
            // Revert back to date fields
            $table->date('start_date')->change();
            $table->date('end_date')->nullable()->change();
        });
    }
};
