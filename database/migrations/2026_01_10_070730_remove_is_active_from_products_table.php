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
        // Drop indexes - try both old and new column names
        \DB::statement('DROP INDEX IF EXISTS products_category_id_is_active_is_public_index');
        \DB::statement('DROP INDEX IF EXISTS products_category_id_is_active_is_visible_in_catalog_index');
        \DB::statement('DROP INDEX IF EXISTS products_is_active_index');
        
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
        
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'is_visible_in_catalog']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'is_visible_in_catalog']);
        });
        
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('is_visible_in_catalog');
        });
        
        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'is_active', 'is_visible_in_catalog']);
            $table->index('is_active');
        });
    }
};
