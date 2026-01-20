<?php

namespace Database\Seeders;

use App\Models\Installment;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class InstallmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find credit sales invoices that have a remaining amount and no existing installments
        $invoices = SalesInvoice::where('payment_method', 'credit')
            ->where('remaining_amount', '>', 0)
            ->whereDoesntHave('installments')
            ->get();

        if ($invoices->isEmpty()) {
            $this->command->info('No eligible credit invoices found for installments.');
            return;
        }

        foreach ($invoices as $invoice) {
            $remainingAmount = $invoice->remaining_amount;
            
            // Split into 2-4 installments
            $numberOfInstallments = rand(2, 4);
            $installmentAmount = floor(($remainingAmount / $numberOfInstallments) * 100) / 100;
            $lastInstallmentAmount = $remainingAmount - ($installmentAmount * ($numberOfInstallments - 1));

            $startDate = Carbon::parse($invoice->created_at);

            for ($i = 1; $i <= $numberOfInstallments; $i++) {
                $amount = ($i == $numberOfInstallments) ? $lastInstallmentAmount : $installmentAmount;
                $dueDate = $startDate->copy()->addMonths($i);
                
                // Check if already overdue
                $status = 'pending';
                if ($dueDate->isPast()) {
                    $status = 'overdue';
                }

                Installment::create([
                    'sales_invoice_id' => $invoice->id,
                    'installment_number' => $i,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'status' => $status,
                    'paid_amount' => 0,
                    'notes' => "قسط رقم {$i} لفاتورة {$invoice->invoice_number}",
                ]);
            }
        }
        
        $this->command->info("Created installments for {$invoices->count()} invoices.");
    }
}
