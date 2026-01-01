<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\TreasuryTransaction;
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
        \Log::info('TreasuryService::recordTransaction called', [
            'treasury_id' => $treasuryId,
            'type' => $type,
            'amount' => $amount,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_level' => DB::transactionLevel(),
        ]);

        $execute = function () use ($treasuryId, $type, $amount, $description, $partnerId, $referenceType, $referenceId) {
            \Log::info('Inside recordTransaction execute closure', [
                'transaction_level' => DB::transactionLevel(),
                'treasury_id' => $treasuryId,
                'amount' => $amount,
            ]);

            // Calculate what the new balance would be
            $currentBalance = (float) $this->getTreasuryBalance($treasuryId);
            $newBalance = $currentBalance + (float) $amount;

            \Log::info('Treasury balance check', [
                'treasury_id' => $treasuryId,
                'current_balance' => $currentBalance,
                'transaction_amount' => (float) $amount,
                'new_balance' => $newBalance,
                'will_fail' => $newBalance < 0,
                'transaction_level' => DB::transactionLevel(),
            ]);

            // Prevent negative balance (amount is negative for withdrawals/payments)
            if ($newBalance < 0) {
                \Log::error('Treasury balance insufficient - throwing exception', [
                    'treasury_id' => $treasuryId,
                    'current_balance' => $currentBalance,
                    'transaction_amount' => (float) $amount,
                    'new_balance' => $newBalance,
                    'transaction_level' => DB::transactionLevel(),
                ]);
                throw new \Exception('لا يمكن إتمام العملية: الرصيد المتاح غير كافٍ في الخزينة');
            }

            \Log::info('Creating treasury transaction', [
                'treasury_id' => $treasuryId,
                'type' => $type,
                'amount' => $amount,
                'transaction_level' => DB::transactionLevel(),
            ]);

            $transaction = TreasuryTransaction::create([
                'treasury_id' => $treasuryId,
                'type' => $type,
                'amount' => $amount,
                'description' => $description,
                'partner_id' => $partnerId,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
            ]);

            \Log::info('Treasury transaction created', [
                'transaction_id' => $transaction->id,
                'treasury_id' => $treasuryId,
                'amount' => $amount,
                'transaction_level' => DB::transactionLevel(),
            ]);

            return $transaction;
        };

        // Only wrap in transaction if not already in one
        $transactionLevel = DB::transactionLevel();
        \Log::info('Checking transaction level before recordTransaction', [
            'transaction_level' => $transactionLevel,
            'will_create_nested' => $transactionLevel === 0,
        ]);

        if ($transactionLevel === 0) {
            return DB::transaction($execute);
        } else {
            return $execute();
        }
    }

    /**
     * Update partner balance using new calculation method
     * This recalculates from invoices, returns, and actual payments
     */
    public function updatePartnerBalance(string $partnerId): void
    {
        $execute = function () use ($partnerId) {
            $partner = Partner::findOrFail($partnerId);
            $partner->recalculateBalance();
        };

        // Only wrap in transaction if not already in one
        if (DB::transactionLevel() === 0) {
            DB::transaction($execute);
        } else {
            $execute();
        }
    }

    /**
     * Get treasury balance from transactions
     * CRITICAL FIX: Uses lockForUpdate() to prevent race conditions
     */
    public function getTreasuryBalance(string $treasuryId): string
    {
        // Use lockForUpdate() to prevent race conditions during concurrent transactions
        $balance = TreasuryTransaction::where('treasury_id', $treasuryId)
            ->lockForUpdate()
            ->sum('amount');

        \Log::info('TreasuryService::getTreasuryBalance', [
            'treasury_id' => $treasuryId,
            'balance' => $balance,
            'transaction_level' => DB::transactionLevel(),
            'transaction_count' => TreasuryTransaction::where('treasury_id', $treasuryId)->count(),
        ]);

        return $balance;
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
     * @param  SalesInvoice|PurchaseInvoice  $invoice
     * @param  float  $amount  Amount being paid
     * @param  float  $discount  Discount given with this payment
     * @param  string|null  $treasuryId  Treasury to record the transaction in
     * @param  string|null  $notes  Optional notes for this payment
     */
    public function recordInvoicePayment(
        $invoice,
        float $amount,
        float $discount = 0,
        ?string $treasuryId = null,
        ?string $notes = null
    ): \App\Models\InvoicePayment {
        if (! $invoice->isPosted()) {
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
                ($isSales ? 'تسديد فاتورة بيع ' : 'تسديد فاتورة شراء ')."#{$invoice->invoice_number}",
                $invoice->partner_id,
                'financial_transaction',
                null
            );

            // Create invoice payment record
            $payment = \App\Models\InvoicePayment::create([
                'payable_type' => $invoice->getMorphClass(),
                'payable_id' => $invoice->id,
                'amount' => $amount,
                'discount' => $discount,
                'payment_date' => now(),
                'notes' => $notes,
                'treasury_transaction_id' => $treasuryTransaction->id,
                'partner_id' => $invoice->partner_id,
                'created_by' => auth()->id(),
            ]);

            // CRITICAL FIX: Update invoice paid_amount to include settlement discount
            // The total settled amount is the cash paid + discount given
            $totalSettled = $amount + $discount;
            $newPaidAmount = floatval($invoice->paid_amount) + $totalSettled;
            $newRemainingAmount = floatval($invoice->total) - $newPaidAmount;

            $invoice->update([
                'paid_amount' => $newPaidAmount,
                'remaining_amount' => max(0, $newRemainingAmount), // Ensure no negative remaining
            ]);

            // Apply payment to installments if they exist
            if ($invoice instanceof \App\Models\SalesInvoice && $invoice->installments()->exists()) {
                app(\App\Services\InstallmentService::class)
                    ->applyPaymentToInstallments($invoice, $payment);
            }

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
        if (! $treasury) {
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
        if (! $invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        $execute = function () use ($invoice, $treasuryId) {
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
        };

        // Only wrap in transaction if not already in one
        if (DB::transactionLevel() === 0) {
            DB::transaction($execute);
        } else {
            $execute();
        }
    }

    /**
     * Post a purchase invoice - creates treasury transaction ONLY for paid_amount
     */
    public function postPurchaseInvoice(PurchaseInvoice $invoice, ?string $treasuryId = null): void
    {
        \Log::info('TreasuryService::postPurchaseInvoice called', [
            'invoice_id' => $invoice->id,
            'invoice_status' => $invoice->status,
            'paid_amount' => $invoice->paid_amount,
            'total' => $invoice->total,
            'transaction_level' => DB::transactionLevel(),
        ]);

        if (! $invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        \Log::info('Treasury ID determined', [
            'treasury_id' => $treasuryId,
            'transaction_level' => DB::transactionLevel(),
        ]);

        $execute = function () use ($invoice, $treasuryId) {
            \Log::info('Inside postPurchaseInvoice execute closure', [
                'invoice_id' => $invoice->id,
                'transaction_level' => DB::transactionLevel(),
            ]);

            $paidAmount = floatval($invoice->paid_amount ?? 0);

            \Log::info('Checking paid amount', [
                'paid_amount' => $paidAmount,
                'will_create_transaction' => $paidAmount > 0,
            ]);

            // ONLY create treasury transaction if actual cash was paid
            if ($paidAmount > 0) {
                \Log::info('Calling recordTransaction for purchase invoice', [
                    'treasury_id' => $treasuryId,
                    'paid_amount' => $paidAmount,
                    'transaction_level' => DB::transactionLevel(),
                ]);

                $this->recordTransaction(
                    $treasuryId,
                    'payment',
                    -$paidAmount, // Negative for payment
                    "Purchase Invoice #{$invoice->invoice_number}",
                    $invoice->partner_id,
                    'purchase_invoice',
                    $invoice->id
                );

                \Log::info('recordTransaction completed successfully', [
                    'invoice_id' => $invoice->id,
                    'transaction_level' => DB::transactionLevel(),
                ]);
            } else {
                \Log::info('Skipping treasury transaction - no paid amount', [
                    'paid_amount' => $paidAmount,
                ]);
            }

            // Update partner balance based on new calculation
            if ($invoice->partner_id) {
                \Log::info('Updating partner balance', [
                    'partner_id' => $invoice->partner_id,
                    'transaction_level' => DB::transactionLevel(),
                ]);
                $this->updatePartnerBalance($invoice->partner_id);
            }
        };

        // Only wrap in transaction if not already in one
        $transactionLevel = DB::transactionLevel();
        \Log::info('Checking transaction level before postPurchaseInvoice', [
            'transaction_level' => $transactionLevel,
            'will_create_nested' => $transactionLevel === 0,
        ]);

        if ($transactionLevel === 0) {
            DB::transaction($execute);
        } else {
            $execute();
        }

        \Log::info('TreasuryService::postPurchaseInvoice completed', [
            'invoice_id' => $invoice->id,
            'transaction_level' => DB::transactionLevel(),
        ]);
    }

    /**
     * Post a sales return - creates treasury transaction ONLY for cash refunds
     */
    public function postSalesReturn(SalesReturn $return, ?string $treasuryId = null): void
    {
        if (! $return->isDraft()) {
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
        if (! $return->isDraft()) {
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
            // Financial transactions adjust both treasury AND partner balances
            // Collection from customer: treasury +amount, partner balance -amount (reduces their debt)
            // Payment to supplier: treasury -amount, partner balance -amount (reduces our debt to them)

            $treasuryAmount = $type === 'payment' ? -abs($amount) : abs($amount);

            if ($discount) {
                $treasuryAmount = $type === 'payment'
                    ? -abs($amount) + abs($discount)
                    : abs($amount) - abs($discount);
            }

            // CRITICAL FIX: For financial transactions, the amount stored should represent
            // the change to partner DEBT (not the cash flow).
            // - Collection: Customer paid us, their debt DECREASES, store NEGATIVE
            // - Payment: We paid supplier, our debt to them DECREASES, store POSITIVE (but supplier balance is negative, so net effect is reduction)
            // Actually, looking at Partner::calculateBalance(), collections are SUBTRACTED from sales
            // So collections should be NEGATIVE to INCREASE the subtraction (thus reducing balance)
            // Wait no - collections are summed and then subtracted. So positive collection = more subtraction = lower balance

            // After analysis: Keep treasury amount as the transaction record
            $transaction = $this->recordTransaction(
                $treasuryId,
                $type,
                $treasuryAmount,
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
                $expense->title.($expense->description ? ': '.$expense->description : ''),
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
                $revenue->title.($revenue->description ? ': '.$revenue->description : ''),
                null,
                'revenue',
                $revenue->id
            );
        });
    }

    /**
     * Post a fixed asset purchase - creates treasury transaction
     */
    public function postFixedAssetPurchase(\App\Models\FixedAsset $asset): void
    {
        DB::transaction(function () use ($asset) {
            $this->recordTransaction(
                $asset->treasury_id,
                'expense', // Reuse existing expense type
                -abs($asset->purchase_amount), // Negative for expense
                'شراء أصل ثابت: ' . $asset->name . ($asset->description ? ' - ' . $asset->description : ''),
                null,
                'fixed_asset',
                $asset->id
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
