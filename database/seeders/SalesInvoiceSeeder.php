<?php

namespace Database\Seeders;

use App\Models\Installment;
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

        // Create 10 draft sales invoices (5 with installments)
        for ($i = 1; $i <= 10; $i++) {
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

            // Add installments for invoices 6-10 (credit invoices with installment plans)
            if ($i > 5 && $paymentMethod === 'credit' && $total > 0) {
                $numberOfInstallments = rand(3, 6); // 3 to 6 installments
                $installmentAmount = $total / $numberOfInstallments;
                $totalInstallmentAmount = 0;

                for ($k = 1; $k <= $numberOfInstallments; $k++) {
                    // For the last installment, adjust to match exact total
                    $amount = ($k === $numberOfInstallments)
                        ? ($total - $totalInstallmentAmount)
                        : $installmentAmount;

                    $totalInstallmentAmount += $amount;

                    // Calculate due date (30 days apart starting from today)
                    $dueDate = now()->addDays($k * 30)->toDateString();

                    // Determine status based on due date and random chance
                    $isPastDue = $dueDate < now()->toDateString();
                    $isPaid = rand(0, 10) < 3; // 30% chance of being paid
                    $isOverdue = $isPastDue && !$isPaid;

                    $status = 'pending';
                    $paidInstallmentAmount = 0;
                    $paidAt = null;
                    $paidBy = null;

                    if ($isPaid) {
                        $status = 'paid';
                        $paidInstallmentAmount = $amount;
                        $paidAt = now()->subDays(rand(1, 60));
                        $paidBy = $user->id;
                    } elseif ($isOverdue) {
                        $status = 'overdue';
                        // 30% chance of partial payment on overdue
                        if (rand(0, 10) < 3) {
                            $paidInstallmentAmount = $amount * (rand(20, 80) / 100);
                            $paidAt = now()->subDays(rand(1, 30));
                            $paidBy = $user->id;
                        }
                    }

                    Installment::create([
                        'sales_invoice_id' => $invoice->id,
                        'installment_number' => $k,
                        'amount' => $amount,
                        'due_date' => $dueDate,
                        'status' => $status,
                        'paid_amount' => $paidInstallmentAmount,
                        'paid_at' => $paidAt,
                        'paid_by' => $paidBy,
                        'notes' => $k === 1 ? 'القسط الأول من خطة التقسيط' : null,
                    ]);
                }
            }
        }
    }
}
