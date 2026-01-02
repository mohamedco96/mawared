<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // ==================================================
            // PHASE 1: SYSTEM FOUNDATION (MUST RUN FIRST)
            // ==================================================

            // General settings
            GeneralSettingSeeder::class,

            // Roles and Permissions (BEFORE users so roles exist)
            RolesAndPermissionsSeeder::class,

            // Users with roles assigned
            AdminUserSeeder::class,

            // ==================================================
            // PHASE 2: BASE DATA STRUCTURE
            // ==================================================

            // Units (required for products)
            UnitSeeder::class,

            // Warehouses (required for invoices)
            WarehouseSeeder::class,

            // Product Categories (required for products)
            ProductCategorySeeder::class,

            // ==================================================
            // PHASE 3: COMPREHENSIVE DATA GENERATION
            // ==================================================

            // Run the comprehensive seeder which creates:
            // - Treasuries (4 treasuries)
            // - Partners (30+ partners: customers, suppliers, shareholders)
            // - Products (50+ products with various stock levels)
            // - Opening Capital (from shareholders)
            // - Purchase Invoices (40 invoices)
            // - Sales Invoices (60 invoices)
            // - Returns (20+ returns)
            // - Expenses (30 expenses)
            // - Revenues (10 revenues)
            // - Treasury Transfers (10 transfers)
            // - Subsequent Payments (25+ payments)
            ComprehensiveDatabaseSeeder::class,

            // ==================================================
            // PHASE 4: ADDITIONAL SEEDERS (OPTIONAL)
            // ==================================================
            // Uncomment if you want to add more specific data:

            // QuotationSeeder::class,
            FixedAssetSeeder::class,
            // HomeGoodsSeeder::class, // If you have specific product data
        ]);
    }
}
