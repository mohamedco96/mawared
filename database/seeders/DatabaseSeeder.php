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
            // PHASE 3: GOLDEN PATH DATA GENERATION
            // ==================================================

            // Run the Golden Path seeder which creates a logically consistent
            // business story with proper chronological order:
            // - Initial Capital (500,000 EGP from shareholders)
            // - Partners (10 customers, 5 suppliers, 3 shareholders)
            // - Products (20 products with realistic pricing)
            // - 30 Days of Business Operations:
            //   * Days 1-10: Purchase Invoices (building inventory)
            //   * Days 5-30: Sales Invoices (selling from available stock)
            //   * Payment collections and supplier payments
            //   * Operating expenses every 5 days
            //   * Occasional returns and revenues
            // - Financial Integrity Verification
            // - Partner Balance Recalculation
            //
            // This ensures:
            // ✓ No negative stock
            // ✓ No financial discrepancies
            // ✓ Balanced treasury accounts
            // ✓ Chronologically correct dates
            GoldenPathSeeder::class,

            // ==================================================
            // PHASE 4: ADDITIONAL SEEDERS (OPTIONAL)
            // ==================================================
            // Uncomment if you want to add more specific data:

            // QuotationSeeder::class,
            FixedAssetSeeder::class,
            // HomeGoodsSeeder::class, // If you have specific product data

            // ==================================================
            // ALTERNATIVE: Use ComprehensiveDatabaseSeeder for random data
            // ==================================================
            // If you prefer the old random data approach, comment out
            // GoldenPathSeeder above and uncomment this:
            // ComprehensiveDatabaseSeeder::class,
        ]);
    }
}
