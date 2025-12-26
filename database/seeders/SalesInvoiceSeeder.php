<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class SalesInvoiceSeeder extends Seeder
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

        // Create 5 draft sales invoices
        for ($i = 1; $i <= 5; $i++) {
            $customer = $customers->random();

            $paymentMethod = $i % 2 == 0 ? 'cash' : 'credit';
            $discountType = rand(0, 1) == 0 ? 'percentage' : 'fixed';

            $invoice = SalesInvoice::create([
                'invoice_number' => 'INV-SALE-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouse->id,
                'partner_id' => $customer->id,
                'status' => 'draft',
                'payment_method' => $paymentMethod,
                'discount_type' => $discountType,
                'discount_value' => 0,
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
                'paid_amount' => 0,
                'remaining_amount' => 0,
                'notes' => 'فاتورة بيع تجريبية رقم ' . $i,
                'created_by' => $user->id,
            ]);

            // Add 2-4 random items to each invoice
            $itemCount = rand(2, 4);
            $subtotal = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products->random();
                $unitType = rand(0, 1) == 0 ? 'small' : 'large';
                $quantity = rand(1, 10);

                $unitPrice = $unitType === 'small'
                    ? $product->retail_price
                    : $product->large_retail_price;

                $itemDiscount = rand(0, 1) == 0 ? 0 : rand(5, 20);
                $itemTotal = ($unitPrice * $quantity) - $itemDiscount;

                SalesInvoiceItem::create([
                    'sales_invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'unit_type' => $unitType,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount' => $itemDiscount,
                    'total' => $itemTotal,
                ]);

                $subtotal += $itemTotal;
            }

            // Update invoice totals
            $discountValue = rand(0, 1) == 0 ? 0 : ($discountType === 'percentage' ? rand(5, 15) : rand(10, 50));
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
