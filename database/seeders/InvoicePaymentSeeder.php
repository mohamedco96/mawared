<?php

namespace Database\Seeders;

use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoicePaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Note: This seeder creates invoice payments without actually creating treasury transactions
     * to avoid triggering business logic. In a real scenario, payments would be created through
     * the application's payment processing system.
     */
    public function run(): void
    {
        $user = User::first();

        // Get some sales invoices with credit payment method
        $salesInvoices = SalesInvoice::where('payment_method', 'credit')
            ->where('status', 'draft')
            ->get();

        // Get some purchase invoices with credit payment method
        $purchaseInvoices = PurchaseInvoice::where('payment_method', 'credit')
            ->where('status', 'draft')
            ->get();

        // Create payments for 2 sales invoices (if available)
        foreach ($salesInvoices->take(2) as $index => $invoice) {
            if ($invoice->remaining_amount > 0) {
                $paymentAmount = $invoice->remaining_amount * (rand(20, 50) / 100);

                InvoicePayment::create([
                    'payable_type' => SalesInvoice::class,
                    'payable_id' => $invoice->id,
                    'amount' => $paymentAmount,
                    'discount' => 0,
                    'payment_date' => now()->subDays(rand(1, 10)),
                    'notes' => 'دفعة جزئية على فاتورة البيع',
                    'treasury_transaction_id' => null,
                    'partner_id' => $invoice->partner_id,
                    'created_by' => $user->id,
                ]);
            }
        }

        // Create payments for 2 purchase invoices (if available)
        foreach ($purchaseInvoices->take(2) as $index => $invoice) {
            if ($invoice->remaining_amount > 0) {
                $paymentAmount = $invoice->remaining_amount * (rand(30, 60) / 100);

                InvoicePayment::create([
                    'payable_type' => PurchaseInvoice::class,
                    'payable_id' => $invoice->id,
                    'amount' => $paymentAmount,
                    'discount' => 0,
                    'payment_date' => now()->subDays(rand(1, 10)),
                    'notes' => 'دفعة جزئية على فاتورة الشراء',
                    'treasury_transaction_id' => null,
                    'partner_id' => $invoice->partner_id,
                    'created_by' => $user->id,
                ]);
            }
        }
    }
}
