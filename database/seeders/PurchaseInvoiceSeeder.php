<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class PurchaseInvoiceSeeder extends Seeder
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

        // Create 5 draft purchase invoices
        for ($i = 1; $i <= 5; $i++) {
            $supplier = $suppliers->random();

            $paymentMethod = $i % 2 == 0 ? 'cash' : 'credit';
            $discountType = rand(0, 1) == 0 ? 'percentage' : 'fixed';

            $invoice = PurchaseInvoice::create([
                'invoice_number' => 'INV-PUR-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouse->id,
                'partner_id' => $supplier->id,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'discount_type' => $discountType,
                'discount_value' => 0,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'notes' => 'فاتورة شراء تجريبية رقم ' . $i,
                'created_by' => $user->id,
            ]);

            // Add 2-5 random items to each invoice
            $itemCount = rand(2, 5);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $unitType = rand(0, 1) == 0 ? 'small' : 'large';
                $quantity = rand(10, 50);

                // Purchase uses cost, not retail price
                $unitCost = $unitType === 'small'
                    ? $product->avg_cost
                    : ($product->avg_cost * $product->factor);

                $itemDiscount = rand(0, 1) == 0 ? 0 : rand(10, 50);
                $itemTotal = ($unitCost * $quantity) - $itemDiscount;

                // Optionally set new selling price for some items
                $newSellingPrice = rand(0, 2) == 0 ? ($unitCost * 1.4) : null;
                $newLargeSellingPrice = ($unitType === 'large' && $newSellingPrice) ? ($newSellingPrice * $product->factor) : null;

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_type' => $unitType,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'discount' => $itemDiscount,
                    'total' => $itemTotal,
                    'new_selling_price' => $newSellingPrice,
                    'new_large_selling_price' => $newLargeSellingPrice,
                ]);

                $subtotal += $itemTotal;
            }

            // Update invoice totals
            $discountValue = rand(0, 1) == 0 ? 0 : ($discountType === 'percentage' ? rand(5, 15) : rand(20, 100));
            $discountAmount = $discountType === 'percentage' ? ($subtotal * $discountValue / 100) : $discountValue;
            $total = $subtotal - $discountAmount;

            // For cash invoices, set paid_amount. For credit, leave it 0 or partial
            $paidAmount = 0;
            if ($paymentMethod === 'cash') {
                $paidAmount = $total;
            } elseif ($paymentMethod === 'credit' && rand(0, 1) == 1) {
                $paidAmount = $total * 0.5;
            }

            $invoice->update([
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount' => $discountAmount,
                'total' => $total,
                'paid_amount' => $paidAmount,
                'remaining_amount' => $total - $paidAmount,
            ]);
        }
    }
}
