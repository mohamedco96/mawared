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
        Schema::create('equity_periods', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedInteger('period_number')->unique();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable()->index();
            $table->enum('status', ['open', 'closed'])->default('open')->index();
            $table->decimal('net_profit', 18, 4)->default(0);
            $table->decimal('total_revenue', 18, 4)->default(0);
            $table->decimal('total_expenses', 18, 4)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('closed_by')->references('id')->on('users')->onDelete('set null');
        });

        // Note: MySQL doesn't support partial unique indexes like PostgreSQL
        // The constraint "only one open period" is enforced in the application layer (CapitalService)
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equity_periods');
    }
};
