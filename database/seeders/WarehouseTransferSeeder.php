<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use Illuminate\Database\Seeder;

class WarehouseTransferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouses = Warehouse::limit(2)->get();
        
        if ($warehouses->count() < 2) {
            $this->command->warn('Skipping WarehouseTransferSeeder: Need at least 2 warehouses.');
            return;
        }

        $fromWarehouse = $warehouses[0];
        $toWarehouse = $warehouses[1];
        
        $user = User::first();
        if (!$user) {
            $this->command->warn('Skipping WarehouseTransferSeeder: No user found.');
            return;
        }

        $products = Product::limit(5)->get();
        if ($products->isEmpty()) {
            $this->command->warn('Skipping WarehouseTransferSeeder: No products found.');
            return;
        }

        // Create 2 warehouse transfers
        for ($i = 1; $i <= 2; $i++) {
            $transfer = WarehouseTransfer::create([
                'transfer_number' => 'TRF-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'from_warehouse_id' => $fromWarehouse->id,
                'to_warehouse_id' => $toWarehouse->id,
                'notes' => 'نقل مخزون تجريبي رقم ' . $i,
                'created_by' => $user->id,
            ]);

            // Add 2-3 random items to each transfer
            $itemCount = rand(2, 3);

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();

                WarehouseTransferItem::create([
                    'warehouse_transfer_id' => $transfer->id,
                    'product_id' => $product->id,
                    'quantity' => rand(5, 20),
                ]);
            }
        }
    }
}
