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
        // First, update any existing negative values to 0
        DB::table('products')->where('min_stock', '<', 0)->update(['min_stock' => 0]);
        DB::table('products')->where('retail_price', '<', 0)->update(['retail_price' => 0]);
        DB::table('products')->where('wholesale_price', '<', 0)->update(['wholesale_price' => 0]);
        DB::table('products')->where('large_retail_price', '<', 0)->update(['large_retail_price' => 0]);
        DB::table('products')->where('large_wholesale_price', '<', 0)->update(['large_wholesale_price' => 0]);

        // Add check constraints to ensure price fields and min_stock cannot be negative
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_min_stock_non_negative CHECK (min_stock >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_retail_price_non_negative CHECK (retail_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_wholesale_price_non_negative CHECK (wholesale_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_large_retail_price_non_negative CHECK (large_retail_price >= 0 OR large_retail_price IS NULL)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_large_wholesale_price_non_negative CHECK (large_wholesale_price >= 0 OR large_wholesale_price IS NULL)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop check constraints
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_min_stock_non_negative');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_retail_price_non_negative');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_wholesale_price_non_negative');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_large_retail_price_non_negative');
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS chk_large_wholesale_price_non_negative');
    }
};
