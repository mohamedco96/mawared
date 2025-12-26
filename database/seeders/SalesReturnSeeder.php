<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class SalesReturnSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $warehouse = Warehouse::where('code', 'WH-CAI-001')->first();
        $customers = Partner::where('type', 'customer')->get();
        $user = User::first();
        $products = Product::all();

        // Create 3 draft sales returns (without invoice reference)
        for ($i = 1; $i <= 3; $i++) {
            $customer = $customers->random();
            $paymentMethod = ['cash', 'credit'][$i % 2];

            $return = SalesReturn::create([
                'return_number' => 'RET-SALE-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouse->id,
                'partner_id' => $customer->id,
                'sales_invoice_id' => null,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'notes' => 'مرتجع بيع تجريبي رقم ' . $i,
                'created_by' => $user->id,
            ]);

            // Add 1-3 random items to each return
            $itemCount = rand(1, 3);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $unitType = rand(0, 1) == 0 ? 'small' : 'large';
                $quantity = rand(1, 5);

                $unitPrice = $unitType === 'small'
                    ? $product->retail_price
                    : $product->large_retail_price;

                $itemDiscount = rand(0, 1) == 0 ? 0 : rand(5, 15);
                $itemTotal = ($unitPrice * $quantity) - $itemDiscount;

                SalesReturnItem::create([
                    'sales_return_id' => $return->id,
                    'product_id' => $product->id,
                    'unit_type' => $unitType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'total' => $itemTotal,
                ]);

                $subtotal += $itemTotal;
            }

            // Update return totals
            $returnDiscount = rand(0, 1) == 0 ? 0 : rand(5, 20);
            $return->update([
                'subtotal' => $subtotal,
                'discount' => $returnDiscount,
                'total' => $subtotal - $returnDiscount,
            ]);
        }
    }
}
