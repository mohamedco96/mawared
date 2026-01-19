<?php

namespace Tests\Feature\Commands;

use App\Models\Expense;
use App\Models\Treasury;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FixFinancialDiscrepanciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_fix_discrepancies_dry_run()
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $expense = Expense::factory()->create([
            'treasury_id' => $treasury->id,
            'amount' => 100,
        ]);

        // Ensure no transaction exists initially
        $this->assertDatabaseCount('treasury_transactions', 1); // 1 for initial capital setup in TestCase

        // Mock TreasuryService
        $mockService = $this->mock(TreasuryService::class);
        $mockService->shouldNotReceive('recordTransaction');

        // ACT
        $this->artisan('finance:fix-discrepancies', ['--dry-run' => true])
            ->expectsOutputToContain('DRY RUN MODE')
            ->expectsOutputToContain('Found expense without treasury transaction')
            ->assertExitCode(0);

        // ASSERT
        $this->assertDatabaseCount('treasury_transactions', 1); // Still 1
    }

    public function test_fix_discrepancies_applies_changes()
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $expense = Expense::factory()->create([
            'treasury_id' => $treasury->id,
            'amount' => 100,
            'title' => 'Test Expense',
        ]);

        // Mock TreasuryService to expect the call
        $mockService = $this->mock(TreasuryService::class);
        $mockService->shouldReceive('recordTransaction')
            ->once()
            ->with(
                $treasury->id,
                'payment',
                Mockery::type('string'), // amount
                "مصروف: {$expense->title} (تسوية)",
                null,
                get_class($expense),
                $expense->id
            );

        // ACT
        $this->artisan('finance:fix-discrepancies')
            ->expectsOutputToContain('Starting financial discrepancy repair')
            ->expectsOutputToContain('Found expense without treasury transaction')
            ->assertExitCode(0);
    }
}
