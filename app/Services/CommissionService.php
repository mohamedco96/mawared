<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(
        private TreasuryService $treasuryService
    ) {}

    /**
     * Calculate commission when invoice is created (before posting)
     */
    public function calculateCommission(SalesInvoice $invoice): void
    {
        if (!$invoice->sales_person_id || !$invoice->commission_rate) {
            $invoice->commission_amount = 0;
            return;
        }

        $rate = floatval($invoice->commission_rate) / 100;
        $commissionAmount = floatval($invoice->total) * $rate;

        $invoice->commission_amount = $commissionAmount;
    }

    /**
     * Pay commission to salesperson (after invoice is posted)
     */
    public function payCommission(SalesInvoice $invoice, string $treasuryId): void
    {
        if (!$invoice->isPosted()) {
            throw new \Exception('لا يمكن دفع عمولة لفاتورة غير مؤكدة');
        }

        if ($invoice->commission_paid) {
            throw new \Exception('تم دفع العمولة مسبقاً');
        }

        if (!$invoice->sales_person_id || $invoice->commission_amount <= 0) {
            throw new \Exception('لا توجد عمولة للدفع');
        }

        DB::transaction(function () use ($invoice, $treasuryId) {
            // Create treasury transaction
            $this->treasuryService->recordTransaction(
                $treasuryId,
                TransactionType::COMMISSION_PAYOUT->value,
                -abs($invoice->commission_amount), // Negative - money leaves treasury
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
     */
    public function reverseCommission(SalesReturn $return, string $treasuryId): void
    {
        if (!$return->sales_invoice_id) {
            return; // No original invoice to reverse
        }

        $originalInvoice = SalesInvoice::find($return->sales_invoice_id);
        if (!$originalInvoice || !$originalInvoice->commission_paid) {
            return; // No commission was paid, nothing to reverse
        }

        // Calculate proportional reversal based on return amount
        $returnRatio = floatval($return->total) / floatval($originalInvoice->total);
        $reversalAmount = floatval($originalInvoice->commission_amount) * $returnRatio;

        DB::transaction(function () use ($return, $originalInvoice, $reversalAmount, $treasuryId) {
            // Create reversal transaction (money returns to treasury)
            $this->treasuryService->recordTransaction(
                $treasuryId,
                TransactionType::COMMISSION_REVERSAL->value,
                abs($reversalAmount), // Positive - money returns to treasury
                "عكس عمولة - مرتجع #{$return->return_number} للفاتورة #{$originalInvoice->invoice_number}",
                null,
                'sales_return',
                $return->id
            );

            // Reduce commission amount on original invoice
            $newCommissionAmount = floatval($originalInvoice->commission_amount) - $reversalAmount;
            $originalInvoice->update([
                'commission_amount' => max(0, $newCommissionAmount),
                // If commission is fully reversed, mark as unpaid
                'commission_paid' => $newCommissionAmount > 0.01,
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
        $unpaidCommission = $totalCommission - $paidCommission;

        return [
            'salesperson' => $salesperson,
            'total_sales' => $totalSales,
            'total_commission' => $totalCommission,
            'paid_commission' => $paidCommission,
            'unpaid_commission' => $unpaidCommission,
            'invoices_count' => $invoices->count(),
        ];
    }
}
