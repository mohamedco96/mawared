<?php

namespace Tests\Feature;

use App\Models\TreasuryTransaction;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreasuryTransactionDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_treasury_transactions_cannot_be_soft_deleted(): void
    {
        $user = User::factory()->create();
        $treasury = Treasury::factory()->create();

        $transaction = TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => 1000.00,
            'description' => 'Test transaction',
        ]);

        // Verify the model does not use SoftDeletes
        $this->assertFalse(
            in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($transaction)),
            'TreasuryTransaction should not use SoftDeletes trait'
        );
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

        // Test that policy prevents deletion
        $this->actingAs($user);

        $this->assertFalse(
            $user->can('delete', $transaction),
            'Users should not be able to delete treasury transactions'
        );

        $this->assertFalse(
            $user->can('forceDelete', $transaction),
            'Users should not be able to force delete treasury transactions'
        );
    }

    public function test_treasury_transaction_table_has_no_deleted_at_column(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('treasury_transactions', 'deleted_at'),
            'Treasury transactions table should not have deleted_at column'
        );
    }
}
