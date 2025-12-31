<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // System settings and users must run first
            GeneralSettingSeeder::class,
            AdminUserSeeder::class,

            // Base data seeders
            UnitSeeder::class,
            WarehouseSeeder::class,
            TreasurySeeder::class,
            PartnerSeeder::class,
            ProductCategorySeeder::class,
            ProductSeeder::class,

            // Transaction seeders (draft only to avoid triggering business logic)
            SalesInvoiceSeeder::class,
            PurchaseInvoiceSeeder::class,
            SalesReturnSeeder::class,
            PurchaseReturnSeeder::class,
            InvoicePaymentSeeder::class,
            StockAdjustmentSeeder::class,
            WarehouseTransferSeeder::class,
            ExpenseSeeder::class,
            RevenueSeeder::class,

            // Quotation seeder
            QuotationSeeder::class,

            // Fixed Assets seeder (added after treasury integration)
            FixedAssetSeeder::class,
        ]);
    }
}
