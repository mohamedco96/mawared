<?php

namespace App\Observers;

use App\Models\PurchaseReturn;

class PurchaseReturnObserver
{
    /**
     * Handle the PurchaseReturn "updated" event.
     * This fires when status changes from draft to posted.
     */
    public function updated(PurchaseReturn $return): void
    {
        // Check if status changed to posted
        if ($return->wasChanged('status') && $return->status === 'posted') {
            $this->handlePosted($return);
        }
    }

    /**
     * Handle when purchase return is posted
     */
    protected function handlePosted(PurchaseReturn $return): void
    {
        // For credit returns, update the partner balance
        // Cash returns are handled by treasury transactions only
        if ($return->payment_method === 'credit') {
            $this->updatePartnerBalance($return);
        }
    }

    /**
     * Update partner balance for credit purchase return
     *
     * When we return goods to a supplier on credit:
     * - If we owe them money (negative balance), it reduces what we owe
     * - If we don't owe them, it creates a receivable (positive balance)
     *
     * The Partner::calculateBalance() method already handles this correctly,
     * so we just need to trigger a recalculation.
     */
    protected function updatePartnerBalance(PurchaseReturn $return): void
    {
        if ($return->partner) {
            $return->partner->recalculateBalance();
        }
    }
}
