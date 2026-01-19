<?php

namespace Tests;

use App\Models\Treasury;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure a treasury exists and is funded for all tests
        // This prevents "Insufficient Balance" errors across the test suite
        $this->ensureFundedTreasury();
    }

    /**
     * Ensure a treasury exists with sufficient funds for testing
     */
    protected function ensureFundedTreasury(): void
    {
        $treasury = Treasury::first();

        if (!$treasury) {
            $treasury = Treasury::factory()->create([
                'name' => 'Main Treasury',
                'type' => 'cash',
            ]);
        }

        // Inject initial capital into the treasury via a direct transaction
        // This simulates the owner's initial capital investment
        \App\Models\TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => 1000000, // 1 million initial capital
            'description' => 'Initial Capital for Testing',
            'reference_type' => 'initial_capital',
            'reference_id' => null,
        ]);
    }
}
