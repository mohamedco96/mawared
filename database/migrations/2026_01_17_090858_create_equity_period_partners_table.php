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
        Schema::create('equity_period_partners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('equity_period_id');
            $table->ulid('partner_id');
            $table->decimal('equity_percentage', 8, 4);
            $table->decimal('capital_at_start', 18, 4)->default(0);
            $table->decimal('profit_allocated', 18, 4)->default(0);
            $table->decimal('capital_injected', 18, 4)->default(0);
            $table->decimal('drawings_taken', 18, 4)->default(0);
            $table->timestamps();

            $table->foreign('equity_period_id')->references('id')->on('equity_periods')->onDelete('cascade');
            $table->foreign('partner_id')->references('id')->on('partners')->onDelete('cascade');
            $table->unique(['equity_period_id', 'partner_id'], 'unique_period_partner');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equity_period_partners');
    }
};
