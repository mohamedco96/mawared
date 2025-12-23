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

            $invoice = SalesInvoice::create([
                'invoice_number' => 'INV-SALE-' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'warehouse_id' => $warehouse->id,
                'partner_id' => $customer->id,
                'status' => 'draft',
                'payment_method' => $i % 2 == 0 ? 'cash' : 'credit',
                'subtotal' => 0,
                'discount' => 0,
                'total' => 0,
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
            $invoiceDiscount = rand(0, 1) == 0 ? 0 : rand(10, 50);
            $invoice->update([
                'subtotal' => $subtotal,
                'discount' => $invoiceDiscount,
                'total' => $subtotal - $invoiceDiscount,
            ]);
        }
    }
}
