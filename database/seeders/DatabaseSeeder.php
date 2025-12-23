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
            // User seeder must run first
            AdminUserSeeder::class,

            // Base data seeders
            UnitSeeder::class,
            WarehouseSeeder::class,
            TreasurySeeder::class,
            PartnerSeeder::class,
            ProductSeeder::class,

            // Transaction seeders (draft only to avoid triggering business logic)
            SalesInvoiceSeeder::class,
            PurchaseInvoiceSeeder::class,
            StockAdjustmentSeeder::class,
            WarehouseTransferSeeder::class,
            ExpenseSeeder::class,
            RevenueSeeder::class,
        ]);
    }
}
