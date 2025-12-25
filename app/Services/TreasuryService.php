<?php

namespace App\Services;

use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\SalesReturn;
use App\Models\PurchaseReturn;
use App\Models\TreasuryTransaction;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TreasuryService
{
    /**
     * Record a treasury transaction (only called when invoice is posted)
     */
    public function recordTransaction(
        string $treasuryId,
        string $type,
        string $amount,
        string $description,
        ?string $partnerId = null,
        ?string $referenceType = null,
        ?string $referenceId = null
    ): TreasuryTransaction {
        return DB::transaction(function () use ($treasuryId, $type, $amount, $description, $partnerId, $referenceType, $referenceId) {
            return TreasuryTransaction::create([
                'treasury_id' => $treasuryId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'partner_id' => $partnerId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);
        });
    }

    /**
     * Update partner balance using new calculation method
     * This recalculates from invoices, returns, and actual payments
     */
    public function updatePartnerBalance(string $partnerId): void
    {
        DB::transaction(function () use ($partnerId) {
            $partner = Partner::findOrFail($partnerId);
            $partner->recalculateBalance();
        });
    }

    /**
     * Get treasury balance from transactions
     */
    public function getTreasuryBalance(string $treasuryId): string
    {
        return TreasuryTransaction::where('treasury_id', $treasuryId)
            ->sum('amount');
    }

    /**
     * Get partner balance from treasury transactions (for display)
     */
    public function getPartnerBalance(string $partnerId): string
    {
        return TreasuryTransaction::where('partner_id', $partnerId)
            ->sum('amount');
    }

    /**
     * Record a subsequent payment on an invoice
     *
     * @param SalesInvoice|PurchaseInvoice $invoice
     * @param float $amount Amount being paid
     * @param float $discount Discount given with this payment
     * @param string|null $treasuryId Treasury to record the transaction in
     * @param string|null $notes Optional notes for this payment
     * @return \App\Models\InvoicePayment
     */
    public function recordInvoicePayment(
        $invoice,
        float $amount,
        float $discount = 0,
        ?string $treasuryId = null,
        ?string $notes = null
    ): \App\Models\InvoicePayment {
        if (!$invoice->isPosted()) {
            throw new \Exception('Cannot record payment on draft invoice');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        return DB::transaction(function () use ($invoice, $amount, $discount, $treasuryId, $notes) {
            $isSales = $invoice instanceof SalesInvoice;
            $transactionType = $isSales ? 'collection' : 'payment';
            $transactionAmount = $isSales ? $amount : -$amount;

            // Create treasury transaction for the actual cash movement
            $treasuryTransaction = $this->recordTransaction(
                $treasuryId,
                $transactionType,
                $transactionAmount,
                ($isSales ? "تسديد فاتورة بيع " : "تسديد فاتورة شراء ") . "#{$invoice->invoice_number}",
                $invoice->partner_id,
                'financial_transaction',
                null
            );

            // Create invoice payment record
            $payment = \App\Models\InvoicePayment::create([
                'payable_type' => get_class($invoice),
                'payable_id' => $invoice->id,
                'amount' => $amount,
                'discount' => $discount,
                'payment_date' => now(),
                'notes' => $notes,
                'treasury_transaction_id' => $treasuryTransaction->id,
                'partner_id' => $invoice->partner_id,
                'created_by' => auth()->id(),
            ]);

            // Update partner balance
            if ($invoice->partner_id) {
                $this->updatePartnerBalance($invoice->partner_id);
            }

            return $payment;
        });
    }

    /**
     * Get default treasury (first treasury or create one)
     */
    private function getDefaultTreasury(): string
    {
        $treasury = \App\Models\Treasury::first();
        if (!$treasury) {
            // Create default treasury if none exists
            $treasury = \App\Models\Treasury::create([
                'name' => 'الخزينة الرئيسية',
                'type' => 'cash',
            ]);
        }
        return $treasury->id;
    }

    /**
     * Post a sales invoice - creates treasury transaction ONLY for paid_amount
     */
    public function postSalesInvoice(SalesInvoice $invoice, ?string $treasuryId = null): void
    {
        if (!$invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($invoice, $treasuryId) {
            $paidAmount = floatval($invoice->paid_amount ?? 0);

            // ONLY create treasury transaction if actual cash was received
            if ($paidAmount > 0) {
                $this->recordTransaction(
                    $treasuryId,
                    'collection',
                    $paidAmount,
                    "Sales Invoice #{$invoice->invoice_number}",
                    $invoice->partner_id,
                    'sales_invoice',
                    $invoice->id
                );
            }

            // Update partner balance based on new calculation
            if ($invoice->partner_id) {
                $this->updatePartnerBalance($invoice->partner_id);
            }
        });
    }

    /**
     * Post a purchase invoice - creates treasury transaction ONLY for paid_amount
     */
    public function postPurchaseInvoice(PurchaseInvoice $invoice, ?string $treasuryId = null): void
    {
        if (!$invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($invoice, $treasuryId) {
            $paidAmount = floatval($invoice->paid_amount ?? 0);

            // ONLY create treasury transaction if actual cash was paid
            if ($paidAmount > 0) {
                $this->recordTransaction(
                    $treasuryId,
                    'payment',
                    -$paidAmount, // Negative for payment
                    "Purchase Invoice #{$invoice->invoice_number}",
                    $invoice->partner_id,
                    'purchase_invoice',
                    $invoice->id
                );
            }

            // Update partner balance based on new calculation
            if ($invoice->partner_id) {
                $this->updatePartnerBalance($invoice->partner_id);
            }
        });
    }

    /**
     * Post a sales return - creates treasury transaction ONLY for cash refunds
     */
    public function postSalesReturn(SalesReturn $return, ?string $treasuryId = null): void
    {
        if (!$return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($return, $treasuryId) {
            // ONLY create treasury transaction if cash refund
            if ($return->payment_method === 'cash') {
                $this->recordTransaction(
                    $treasuryId,
                    'refund',
                    -$return->total, // NEGATIVE - money leaves treasury
                    "مرتجع فاتورة بيع #{$return->return_number}",
                    $return->partner_id,
                    'sales_return',
                    $return->id
                );
            }

            // For credit returns, no treasury transaction needed
            // The return reduces what customer owes (calculated in Partner model)

            // Update partner balance
            if ($return->partner_id) {
                $this->updatePartnerBalance($return->partner_id);
            }
        });
    }

    /**
     * Post a purchase return - creates treasury transaction ONLY for cash refunds
     */
    public function postPurchaseReturn(PurchaseReturn $return, ?string $treasuryId = null): void
    {
        if (!$return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($return, $treasuryId) {
            // ONLY create treasury transaction if cash refund
            if ($return->payment_method === 'cash') {
                $this->recordTransaction(
                    $treasuryId,
                    'refund',
                    $return->total, // POSITIVE - money returns to treasury
                    "مرتجع فاتورة شراء #{$return->return_number}",
                    $return->partner_id,
                    'purchase_return',
                    $return->id
                );
            }

            // For credit returns, no treasury transaction needed
            // The return reduces what we owe supplier (calculated in Partner model)

            // Update partner balance
            if ($return->partner_id) {
                $this->updatePartnerBalance($return->partner_id);
            }
        });
    }

    /**
     * Record a financial transaction (collection/payment)
     */
    public function recordFinancialTransaction(
        string $treasuryId,
        string $type, // 'collection' or 'payment'
        string $amount,
        string $description,
        ?string $partnerId = null,
        ?string $discount = null
    ): TreasuryTransaction {
        return DB::transaction(function () use ($treasuryId, $type, $amount, $description, $partnerId, $discount) {
            // Financial transactions reduce partner balances (opposite of invoices)
            // Collection from customer: treasury +, partner - (reduces debt)
            // Payment to supplier: treasury -, partner + (reduces what we owe, but their balance is negative so it becomes less negative)

            $treasuryAmount = $type === 'payment' ? -abs($amount) : abs($amount);

            if ($discount) {
                $treasuryAmount = $type === 'payment'
                    ? -abs($amount) + abs($discount)
                    : abs($amount) - abs($discount);
            }

            // Partner amount is opposite of treasury amount for financial transactions
            $partnerAmount = -$treasuryAmount;

            $transaction = $this->recordTransaction(
                $treasuryId,
                $type,
                $partnerAmount,
                $description,
                $partnerId,
                'financial_transaction',
                null
            );

            // Update partner balance if partner is involved
            if ($partnerId) {
                $this->updatePartnerBalance($partnerId);
            }

            return $transaction;
        });
    }

    /**
     * Post an expense - creates treasury transaction
     */
    public function postExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $this->recordTransaction(
                $expense->treasury_id,
                'expense',
                -abs($expense->amount), // Negative for expense
                $expense->title . ($expense->description ? ': ' . $expense->description : ''),
                null,
                'expense',
                $expense->id
            );
        });
    }

    /**
     * Post a revenue - creates treasury transaction
     */
    public function postRevenue(Revenue $revenue): void
    {
        DB::transaction(function () use ($revenue) {
            $this->recordTransaction(
                $revenue->treasury_id,
                'income',
                abs($revenue->amount), // Positive for income
                $revenue->title . ($revenue->description ? ': ' . $revenue->description : ''),
                null,
                'revenue',
                $revenue->id
            );
        });
    }

    /**
     * Record employee advance payment
     */
    public function recordEmployeeAdvance(
        string $treasuryId,
        string $amount,
        string $description,
        string $employeeId
    ): TreasuryTransaction {
        return DB::transaction(function () use ($treasuryId, $amount, $description, $employeeId) {
            $transaction = TreasuryTransaction::create([
                'treasury_id' => $treasuryId,
                'type' => 'employee_advance',
                'amount' => -abs($amount), // Negative - money leaves treasury
                'description' => $description,
                'employee_id' => $employeeId,
                'reference_type' => 'employee_advance',
                'reference_id' => null,
            ]);

            // Update employee advance balance
            $user = User::findOrFail($employeeId);
            if (Schema::hasColumn('users', 'advance_balance')) {
                $user->increment('advance_balance', abs($amount));
            }

            return $transaction;
        });
    }
}

