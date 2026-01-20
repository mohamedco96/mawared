<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\InvoicePayment;
use App\Models\SalesInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InstallmentService
{
    /**
     * Generate installment schedule for an invoice
     * Called after invoice is posted
     *
     * @throws \Exception
     */
    public function generateInstallmentSchedule(SalesInvoice $invoice): void
    {
        // Validation
        if (! $invoice->has_installment_plan) {
            return; // No plan defined
        }

        if (! $invoice->isPosted()) {
            throw new \Exception('الفاتورة يجب أن تكون مرحّلة لتوليد الأقساط');
        }

        if ($invoice->remaining_amount <= 0) {
            throw new \Exception('لا يوجد مبلغ متبقي للتقسيط');
        }

        if ($invoice->installments()->exists()) {
            throw new \Exception('خطة الأقساط موجودة بالفعل لهذه الفاتورة');
        }

        if ($invoice->installment_months <= 0) {
            throw new \Exception('عدد الأقساط يجب أن يكون أكبر من الصفر');
        }

        // Calculate installment amount
        $totalToInstall = (string) $invoice->remaining_amount;

        // Apply surcharge if percentage is set
        if ($invoice->installment_interest_percentage > 0) {
            $percentage = (string) $invoice->installment_interest_percentage;

            // Calculate interest amount: remaining_amount * (percentage / 100)
            $interestAmount = bcmul($totalToInstall, bcdiv($percentage, '100', 4), 4);

            // Update invoice with new totals
            $invoice->installment_interest_amount = $interestAmount;

            // Update total and remaining amount to include surcharge
            $newRemaining = bcadd($totalToInstall, $interestAmount, 4);
            $newTotal = bcadd((string) $invoice->total, $interestAmount, 4);

            $invoice->remaining_amount = $newRemaining;
            $invoice->total = $newTotal;
            $invoice->saveQuietly(); // Prevent triggering observers

            // Use the new total for installment calculation
            $totalToInstall = $newRemaining;
        }

        $months = $invoice->installment_months;
        $startDate = Carbon::parse($invoice->installment_start_date);

        // Use precise division with proper rounding
        $installmentAmount = bcdiv($totalToInstall, (string) $months, 4);

        // Handle rounding difference in last installment
        $totalAllocated = bcmul($installmentAmount, (string) ($months - 1), 4);
        $lastInstallmentAmount = bcsub($totalToInstall, $totalAllocated, 4);

        // Create installment records
        $installments = [];
        for ($i = 1; $i <= $months; $i++) {
            $dueDate = (clone $startDate)->addMonths($i - 1)->startOfDay();
            $amount = ($i === $months) ? $lastInstallmentAmount : $installmentAmount;

            $installments[] = [
                'sales_invoice_id' => $invoice->id,
                'installment_number' => $i,
                'amount' => $amount,
                'due_date' => $dueDate->format('Y-m-d'),
                'status' => 'pending',
                'paid_amount' => '0.0000',
                'notes' => $invoice->installment_notes,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Installment::insert($installments);

        // Log activity
        activity()
            ->performedOn($invoice)
            ->withProperties([
                'installments_count' => $months,
                'total_amount' => $totalToInstall,
                'installment_amount' => $installmentAmount,
                'start_date' => $startDate->format('Y-m-d'),
                'interest_percentage' => $invoice->installment_interest_percentage,
                'interest_amount' => $invoice->installment_interest_amount,
            ])
            ->log('تم توليد خطة الأقساط');
    }

    /**
     * Apply payment to installments (FIFO - oldest first)
     */
    public function applyPaymentToInstallments(
        SalesInvoice $invoice,
        InvoicePayment $payment
    ): void {
        DB::transaction(function () use ($invoice, $payment) {
            $remainingPayment = (string) $payment->amount;

            // CRITICAL: Lock installments to prevent race conditions
            $installments = $invoice->installments()
                ->where('status', '!=', 'paid')
                ->orderBy('due_date')
                ->lockForUpdate() // Prevents concurrent payment processing
                ->get();

            foreach ($installments as $installment) {
                if (bccomp($remainingPayment, '0', 4) <= 0) {
                    break;
                }

                $amountDue = bcsub((string) $installment->amount, (string) $installment->paid_amount, 4);

                // Apply only what's needed (prevent overpayment of individual installment)
                $amountToApply = bccomp($remainingPayment, $amountDue, 4) === 1
                    ? $amountDue
                    : $remainingPayment;

                $installment->paid_amount = bcadd((string) $installment->paid_amount, $amountToApply, 4);

                // STRICT equality check (not >=)
                if (bccomp((string) $installment->paid_amount, (string) $installment->amount, 4) === 0) {
                    $installment->status = 'paid';
                    $installment->paid_at = now();
                    $installment->paid_by = auth()->id();
                    $installment->invoice_payment_id = $payment->id;
                }

                $installment->save();

                $remainingPayment = bcsub($remainingPayment, $amountToApply, 4);
            }

            // Track unapplied payment (overpayment scenario)
            if (bccomp($remainingPayment, '0', 4) === 1) {
                activity()
                    ->performedOn($invoice)
                    ->withProperties([
                        'payment_id' => $payment->id,
                        'overpayment_amount' => $remainingPayment,
                    ])
                    ->log('تحذير: دفعة تزيد عن إجمالي الأقساط المتبقية');
            }
        });
    }

    /**
     * Update overdue installments status (run via scheduled task)
     *
     * @return int Number of installments updated
     */
    public function updateOverdueInstallments(): int
    {
        return Installment::where('status', 'pending')
            ->where('due_date', '<', now()->format('Y-m-d'))
            ->update(['status' => 'overdue']);
    }
}
