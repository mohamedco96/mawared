<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class PurchaseReturnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::where('code', 'WH-CAI-001')->first();
        $suppliers = Partner::where('type', 'supplier')->get();
        $user = User::first();
        $products = Product::all();

        // Create 3 draft purchase returns (without invoice reference)
        for ($i = 1; $i <= 3; $i++) {
            $supplier = $suppliers->random();
            $paymentMethod = ['cash', 'credit'][$i % 2];

            $return = PurchaseReturn::create([
                'return_number' => 'RET-PUR-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouse->id,
                'partner_id' => $supplier->id,
                'purchase_invoice_id' => null,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'notes' => 'مرتجع شراء تجريبي رقم ' . $i,
                'created_by' => $user->id,
            ]);

            // Add 1-3 random items to each return
            $itemCount = rand(1, 3);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $unitType = rand(0, 1) == 0 ? 'small' : 'large';
                $quantity = rand(1, 10);

                $unitCost = $unitType === 'small'
                    ? $product->avg_cost
                    : ($product->avg_cost * $product->factor);

                $itemDiscount = rand(0, 1) == 0 ? 0 : rand(5, 30);
                $itemTotal = ($unitCost * $quantity) - $itemDiscount;

                PurchaseReturnItem::create([
                    'purchase_return_id' => $return->id,
                    'product_id' => $product->id,
                    'unit_type' => $unitType,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'discount' => $itemDiscount,
                    'total' => $itemTotal,
                ]);

                $subtotal += $itemTotal;
            }

            // Update return totals
            $returnDiscount = rand(0, 1) == 0 ? 0 : rand(10, 50);
            $return->update([
                'subtotal' => $subtotal,
                'discount' => $returnDiscount,
                'total' => $subtotal - $returnDiscount,
            ]);
        }
    }
}
