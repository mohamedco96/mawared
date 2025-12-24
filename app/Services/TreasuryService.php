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
     * Update partner balance from treasury transactions
     */
    public function updatePartnerBalance(string $partnerId): void
    {
        DB::transaction(function () use ($partnerId) {
            $partner = Partner::findOrFail($partnerId);
            
            // Calculate balance from all treasury transactions
            $balance = TreasuryTransaction::where('partner_id', $partnerId)
                ->sum('amount');

            $partner->update(['current_balance' => $balance]);
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
     * Post a sales invoice - creates treasury transactions
     */
    public function postSalesInvoice(SalesInvoice $invoice, ?string $treasuryId = null): void
    {
        if (!$invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($invoice, $treasuryId) {
            $paidAmount = floatval($invoice->paid_amount ?? 0);
            $remainingAmount = floatval($invoice->remaining_amount ?? 0);

            if ($invoice->payment_method === 'cash') {
                // Cash payment - record paid amount only
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
            } else {
                // Credit payment - record paid amount and update balance for remaining
                if ($paidAmount > 0) {
                    // Record cash portion if any
                    $this->recordTransaction(
                        $treasuryId,
                        'collection',
                        $paidAmount,
                        "Sales Invoice #{$invoice->invoice_number} (Paid Portion)",
                        $invoice->partner_id,
                        'sales_invoice',
                        $invoice->id
                    );
                }

                if ($remainingAmount > 0) {
                    // Record credit portion (affects partner balance)
                    $this->recordTransaction(
                        $treasuryId,
                        'collection',
                        $remainingAmount,
                        "Sales Invoice #{$invoice->invoice_number} (Credit)",
                        $invoice->partner_id,
                        'sales_invoice',
                        $invoice->id
                    );
                }
            }

            // Always update partner balance when there's remaining amount
            if ($remainingAmount > 0 && $invoice->partner_id) {
                $this->updatePartnerBalance($invoice->partner_id);
            }
        });
    }

    /**
     * Post a purchase invoice - creates treasury transactions
     */
    public function postPurchaseInvoice(PurchaseInvoice $invoice, ?string $treasuryId = null): void
    {
        if (!$invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($invoice, $treasuryId) {
            $paidAmount = floatval($invoice->paid_amount ?? 0);
            $remainingAmount = floatval($invoice->remaining_amount ?? 0);

            if ($invoice->payment_method === 'cash') {
                // Cash payment - record paid amount only
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
            } else {
                // Credit payment - record paid amount and update balance for remaining
                if ($paidAmount > 0) {
                    // Record cash portion if any
                    $this->recordTransaction(
                        $treasuryId,
                        'payment',
                        -$paidAmount, // Negative for payment
                        "Purchase Invoice #{$invoice->invoice_number} (Paid Portion)",
                        $invoice->partner_id,
                        'purchase_invoice',
                        $invoice->id
                    );
                }

                if ($remainingAmount > 0) {
                    // Record credit portion (affects partner balance)
                    $this->recordTransaction(
                        $treasuryId,
                        'payment',
                        -$remainingAmount, // Negative for payment
                        "Purchase Invoice #{$invoice->invoice_number} (Credit)",
                        $invoice->partner_id,
                        'purchase_invoice',
                        $invoice->id
                    );
                }
            }

            // Always update partner balance when there's remaining amount
            if ($remainingAmount > 0 && $invoice->partner_id) {
                $this->updatePartnerBalance($invoice->partner_id);
            }
        });
    }

    /**
     * Post a sales return - creates treasury transactions (REFUND to customer)
     */
    public function postSalesReturn(SalesReturn $return, ?string $treasuryId = null): void
    {
        if (!$return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($return, $treasuryId) {
            if ($return->payment_method === 'cash') {
                // Cash refund - create refund transaction (money leaves treasury)
                $this->recordTransaction(
                    $treasuryId,
                    'refund',
                    -$return->total, // NEGATIVE - money leaves treasury
                    "مرتجع فاتورة بيع #{$return->return_number}",
                    $return->partner_id,
                    'sales_return',
                    $return->id
                );
            } else {
                // Credit refund - reduce partner's debt
                $this->recordTransaction(
                    $treasuryId,
                    'refund',
                    -$return->total, // NEGATIVE - reduces what customer owes us
                    "مرتجع فاتورة بيع #{$return->return_number} (آجل)",
                    $return->partner_id,
                    'sales_return',
                    $return->id
                );
            }

            // Update partner balance if credit
            if ($return->payment_method === 'credit' && $return->partner_id) {
                $this->updatePartnerBalance($return->partner_id);
            }
        });
    }

    /**
     * Post a purchase return - creates treasury transactions (REFUND from supplier)
     */
    public function postPurchaseReturn(PurchaseReturn $return, ?string $treasuryId = null): void
    {
        if (!$return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        $treasuryId = $treasuryId ?? $this->getDefaultTreasury();

        DB::transaction(function () use ($return, $treasuryId) {
            if ($return->payment_method === 'cash') {
                // Cash refund - create refund transaction (money returns to treasury)
                $this->recordTransaction(
                    $treasuryId,
                    'refund',
                    $return->total, // POSITIVE - money returns to treasury
                    "مرتجع فاتورة شراء #{$return->return_number}",
                    $return->partner_id,
                    'purchase_return',
                    $return->id
                );
            } else {
                // Credit refund - reduce our debt to supplier
                $this->recordTransaction(
                    $treasuryId,
                    'refund',
                    $return->total, // POSITIVE - reduces what we owe supplier
                    "مرتجع فاتورة شراء #{$return->return_number} (آجل)",
                    $return->partner_id,
                    'purchase_return',
                    $return->id
                );
            }

            // Update partner balance if credit
            if ($return->payment_method === 'credit' && $return->partner_id) {
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

