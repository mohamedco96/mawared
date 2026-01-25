<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add fields to support non-cash expenses (like depreciation):
     * - is_non_cash: Marks expenses that don't affect treasury balance
     * - fixed_asset_id: Links depreciation expenses to the asset being depreciated
     * - depreciation_period: The month/year this depreciation applies to
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Flag for non-cash expenses (depreciation doesn't affect cash)
            $table->boolean('is_non_cash')->default(false)->after('expense_date');

            // Link to fixed asset for depreciation expenses
            $table->foreignUlid('fixed_asset_id')
                ->nullable()
                ->after('is_non_cash')
                ->constrained('fixed_assets')
                ->onDelete('cascade');

            // The accounting period this depreciation applies to (YYYY-MM-01)
            $table->date('depreciation_period')->nullable()->after('fixed_asset_id');

            $table->index('is_non_cash');
            $table->index('fixed_asset_id');
            $table->index('depreciation_period');
        });

        // Make treasury_id nullable for non-cash expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignUlid('treasury_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['fixed_asset_id']);
            $table->dropIndex(['is_non_cash']);
            $table->dropIndex(['depreciation_period']);
            $table->dropColumn(['is_non_cash', 'fixed_asset_id', 'depreciation_period']);
        });
    }
};
