<?php

namespace Tests\Feature;

use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TreasuryTransactionDeletionTest
 *
 * BLIND-04 FIX: Updated tests to reflect that TreasuryTransaction now uses SoftDeletes
 * for audit compliance. Financial transactions should never be hard-deleted but can be
 * soft-deleted for voiding/cancellation purposes while maintaining audit trail.
 */
class TreasuryTransactionDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_treasury_transactions_use_soft_deletes(): void
    {
        $user = User::factory()->create();
        $treasury = Treasury::factory()->create();

        $transaction = TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => 1000.00,
            'description' => 'Test transaction',
        ]);

        // BLIND-04: Verify the model uses SoftDeletes for audit compliance
        $this->assertTrue(
            in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($transaction)),
            'TreasuryTransaction should use SoftDeletes trait for audit compliance'
        );
    }

    public function test_soft_deleted_transactions_are_excluded_from_queries(): void
    {
        $treasury = Treasury::factory()->create();

        $transaction = TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => 1000.00,
            'description' => 'Test transaction',
        ]);

        $transactionId = $transaction->id;

        // Soft delete
        $transaction->delete();

        // Should not appear in normal queries
        $this->assertNull(TreasuryTransaction::find($transactionId));

        // Should still be accessible with withTrashed
        $this->assertNotNull(TreasuryTransaction::withTrashed()->find($transactionId));
    }

    public function test_treasury_transaction_policy_prevents_deletion(): void
    {
        $user = User::factory()->create();
        $treasury = Treasury::factory()->create();

        $transaction = TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => 1000.00,
            'description' => 'Test transaction',
        ]);

        // Test that policy prevents deletion (UI-level protection)
        $this->actingAs($user);

        $this->assertFalse(
            $user->can('delete', $transaction),
            'Users should not be able to delete treasury transactions via UI'
        );

        $this->assertFalse(
            $user->can('forceDelete', $transaction),
            'Users should not be able to force delete treasury transactions'
        );
    }

    public function test_treasury_transaction_table_has_deleted_at_column(): void
    {
        // BLIND-04: Table should now have deleted_at column for soft deletes
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('treasury_transactions', 'deleted_at'),
            'Treasury transactions table should have deleted_at column for audit compliance'
        );
    }
}
