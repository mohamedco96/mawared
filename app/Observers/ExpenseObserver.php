<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\TreasuryService;

class ExpenseObserver
{
    protected TreasuryService $treasuryService;

    public function __construct(TreasuryService $treasuryService)
    {
        $this->treasuryService = $treasuryService;
    }

    /**
     * Handle the Expense "created" event.
     */
    public function created(Expense $expense): void
    {
        // Only create treasury transaction if one doesn't already exist
        // This prevents duplicate transactions during data repair
        $existingTransaction = $expense->treasuryTransactions()->first();

        if (!$existingTransaction) {
            $this->createTreasuryTransaction($expense);
        }
    }

    /**
     * Create treasury transaction for expense
     */
    protected function createTreasuryTransaction(Expense $expense): void
    {
        $this->treasuryService->recordTransaction(
            treasuryId: $expense->treasury_id,
            type: 'payment',
            amount: (string) (-1 * abs(floatval($expense->amount))), // Negative amount for payment
            description: "مصروف: {$expense->title}",
            partnerId: null,
            referenceType: get_class($expense),
            referenceId: $expense->id
        );
    }
}
