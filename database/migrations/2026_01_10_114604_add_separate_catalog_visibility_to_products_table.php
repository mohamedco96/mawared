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
        Schema::table('products', function (Blueprint $table) {
            // Add new separate visibility fields
            $table->boolean('is_visible_in_retail_catalog')->default(false)->after('is_visible_in_catalog');
            $table->boolean('is_visible_in_wholesale_catalog')->default(false)->after('is_visible_in_retail_catalog');
        });

        // Migrate existing data: if is_visible_in_catalog is true, enable both
        DB::table('products')
            ->where('is_visible_in_catalog', true)
            ->update([
                'is_visible_in_retail_catalog' => true,
                'is_visible_in_wholesale_catalog' => true,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_visible_in_retail_catalog', 'is_visible_in_wholesale_catalog']);
        });
    }
};
