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
        Schema::create('partners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->enum('type', ['customer', 'supplier']);
            $table->string('gov_id')->nullable()->comment('National ID');
            $table->string('region')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->decimal('current_balance', 18, 4)->default(0)->comment('Auto-calculated from treasury_transactions');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['type']);
            $table->index(['current_balance']);
            $table->index(['gov_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
