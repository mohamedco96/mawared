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
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUlid('expense_category_id')
                ->nullable()
                ->after('created_by')
                ->constrained('expense_categories')
                ->onDelete('set null');
            $table->string('beneficiary_name')->nullable()->after('expense_category_id');
            $table->string('attachment')->nullable()->after('beneficiary_name');

            $table->index('expense_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropColumn(['expense_category_id', 'beneficiary_name', 'attachment']);
        });
    }
};
