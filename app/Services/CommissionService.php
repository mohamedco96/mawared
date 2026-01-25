<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(
        private TreasuryService $treasuryService
    ) {}

    /**
     * Calculate commission when invoice is created (before posting)
     *
     * CRITICAL-08 FIX: Uses BC Math for precise commission calculation
     */
    public function calculateCommission(SalesInvoice $invoice): void
    {
        if (! $invoice->sales_person_id || ! $invoice->commission_rate) {
            $invoice->commission_amount = 0;

            return;
        }

        // CRITICAL-08 FIX: Use BC Math for commission calculation
        // commissionAmount = total * (rate / 100)
        $rateDecimal = bcdiv((string) $invoice->commission_rate, '100', 6);
        $commissionAmount = bcmul((string) $invoice->total, $rateDecimal, 4);

        $invoice->commission_amount = $commissionAmount;
    }

    /**
     * Pay commission to salesperson (after invoice is posted)
     */
    public function payCommission(SalesInvoice $invoice, string $treasuryId): void
    {
        if (! $invoice->isPosted()) {
            throw new \Exception('لا يمكن دفع عمولة لفاتورة غير مؤكدة');
        }

        if ($invoice->commission_paid) {
            throw new \Exception('تم دفع العمولة مسبقاً');
        }

        if (! $invoice->sales_person_id || bccomp((string) $invoice->commission_amount, '0', 4) <= 0) {
            throw new \Exception('لا توجد عمولة للدفع');
        }

        DB::transaction(function () use ($invoice, $treasuryId) {
            // Idempotency check: prevent duplicate commission payouts
            $existingTransaction = TreasuryTransaction::where('type', TransactionType::COMMISSION_PAYOUT->value)
                ->where('reference_type', 'sales_invoice')
                ->where('reference_id', $invoice->id)
                ->first();

            if ($existingTransaction) {
                \Log::warning("Commission for SalesInvoice {$invoice->id} already paid. Transaction: {$existingTransaction->id}");

                return;
            }

            // Create treasury transaction
            $this->treasuryService->recordTransaction(
                $treasuryId,
                TransactionType::COMMISSION_PAYOUT->value,
                '-'.abs($invoice->commission_amount), // Negative - money leaves treasury
                "عمولة مبيعات - فاتورة #{$invoice->invoice_number}",
                null, // No partner_id - commission is for employee (user)
                'sales_invoice',
                $invoice->id
            );

            // Mark commission as paid
            $invoice->update(['commission_paid' => true]);
        });
    }

    /**
     * Reverse commission when sale is returned (proportional reversal)
     *
     * CRITICAL-09 FIX: Uses BC Math for precise reversal calculation
     */
    public function reverseCommission(SalesReturn $return, string $treasuryId): void
    {
        if (! $return->sales_invoice_id) {
            return; // No original invoice to reverse
        }

        $originalInvoice = SalesInvoice::find($return->sales_invoice_id);
        if (! $originalInvoice || ! $originalInvoice->commission_paid) {
            return; // No commission was paid, nothing to reverse
        }

        // Prevent division by zero
        if (bccomp((string) $originalInvoice->total, '0', 4) === 0) {
            return;
        }

        // CRITICAL-09 FIX: Use BC Math for precise ratio and reversal calculation
        // returnRatio = return.total / originalInvoice.total
        // reversalAmount = originalInvoice.commission_amount * returnRatio
        $returnRatio = bcdiv((string) $return->total, (string) $originalInvoice->total, 6);
        $reversalAmount = bcmul((string) $originalInvoice->commission_amount, $returnRatio, 4);

        DB::transaction(function () use ($return, $originalInvoice, $reversalAmount, $treasuryId) {
            // Idempotency check: prevent duplicate commission reversals
            $existingTransaction = TreasuryTransaction::where('type', TransactionType::COMMISSION_REVERSAL->value)
                ->where('reference_type', 'sales_return')
                ->where('reference_id', $return->id)
                ->first();

            if ($existingTransaction) {
                \Log::warning("Commission reversal for SalesReturn {$return->id} already exists. Transaction: {$existingTransaction->id}");

                return;
            }

            // Create reversal transaction (money returns to treasury)
            $this->treasuryService->recordTransaction(
                $treasuryId,
                TransactionType::COMMISSION_REVERSAL->value,
                $reversalAmount, // Positive - money returns to treasury (already absolute from bcmul)
                "عكس عمولة - مرتجع #{$return->return_number} للفاتورة #{$originalInvoice->invoice_number}",
                null,
                'sales_return',
                $return->id
            );

            // Reduce commission amount on original invoice using BC Math
            $newCommissionAmount = bcsub((string) $originalInvoice->commission_amount, $reversalAmount, 4);

            // Ensure non-negative
            if (bccomp($newCommissionAmount, '0', 4) < 0) {
                $newCommissionAmount = '0';
            }

            $originalInvoice->update([
                'commission_amount' => $newCommissionAmount,
                // If commission is fully reversed (less than 0.01), mark as unpaid
                'commission_paid' => bccomp($newCommissionAmount, '0.01', 4) >= 0,
            ]);
        });
    }

    /**
     * Get salesperson commission report
     */
    public function getSalespersonReport(User $salesperson, $fromDate, $toDate): array
    {
        $invoices = $salesperson->salesInvoices()
            ->where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->get();

        $totalSales = $invoices->sum('total');
        $totalCommission = $invoices->sum('commission_amount');
        $paidCommission = $invoices->where('commission_paid', true)->sum('commission_amount');
        $unpaidCommission = bcsub((string) $totalCommission, (string) $paidCommission, 4);

        return [
            'salesperson' => $salesperson,
            'total_sales' => $totalSales,
            'total_commission' => $totalCommission,
            'paid_commission' => $paidCommission,
            'unpaid_commission' => (float) $unpaidCommission,
            'invoices_count' => $invoices->count(),
        ];
    }
}
