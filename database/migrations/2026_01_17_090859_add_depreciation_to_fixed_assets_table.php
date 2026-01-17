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
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->unsignedInteger('useful_life_years')->nullable()->after('status');
            $table->decimal('salvage_value', 18, 4)->default(0)->after('useful_life_years');
            $table->decimal('accumulated_depreciation', 18, 4)->default(0)->after('salvage_value');
            $table->date('last_depreciation_date')->nullable()->after('accumulated_depreciation');
            $table->enum('depreciation_method', ['straight_line'])->default('straight_line')->after('last_depreciation_date');
            $table->boolean('is_contributed_asset')->default(false)->after('depreciation_method');
            $table->ulid('contributing_partner_id')->nullable()->after('is_contributed_asset');

            $table->foreign('contributing_partner_id')->references('id')->on('partners')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropForeign(['contributing_partner_id']);
            $table->dropColumn([
                'useful_life_years',
                'salvage_value',
                'accumulated_depreciation',
                'last_depreciation_date',
                'depreciation_method',
                'is_contributed_asset',
                'contributing_partner_id'
            ]);
        });
    }
};
