<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class StockAdjustmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::first();
        if (!$warehouse) {
            $this->command->warn('Skipping StockAdjustmentSeeder: No warehouse found.');
            return;
        }

        $user = User::first();
        if (!$user) {
            $this->command->warn('Skipping StockAdjustmentSeeder: No user found.');
            return;
        }

        $products = Product::limit(5)->get();
        if ($products->isEmpty()) {
            $this->command->warn('Skipping StockAdjustmentSeeder: No products found.');
            return;
        }

        // Create 3 draft stock adjustments
        $types = ['damage', 'opening', 'gift', 'other'];

        foreach ($products->take(3) as $index => $product) {
            $type = $types[$index % count($types)];
            // Positive for increase, negative for decrease
            $quantity = $index % 2 == 0 ? rand(10, 50) : -rand(10, 50);

            StockAdjustment::create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'status' => 'draft',
                'type' => $type,
                'quantity' => $quantity,
                'notes' => "تعديل مخزون تجريبي - {$type} - {$product->name}",
                'created_by' => $user->id,
            ]);
        }
    }
}
