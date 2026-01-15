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
        // Drop indexes - silently ignore if they don't exist
        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_category_id_is_visible_in_catalog_index');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_is_public_index');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        try {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_is_visible_in_catalog_index');
            });
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_visible_in_catalog');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_visible_in_catalog')->default(false)->after('large_wholesale_price');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['category_id', 'is_visible_in_catalog']);
        });
    }
};
