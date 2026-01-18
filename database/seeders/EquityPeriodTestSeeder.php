<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\Treasury;
use App\Models\Revenue;
use App\Models\Expense;
use App\Models\EquityPeriod;
use App\Models\EquityPeriodPartner;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\CapitalService;
use App\Services\TreasuryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EquityPeriodTestSeeder extends Seeder
{
    private CapitalService $capitalService;
    private TreasuryService $treasuryService;
    private User $admin;
    private Treasury $treasury;

    /**
     * Run comprehensive equity period tests
     */
    public function run(): void
    {
        $this->comment('====================================');
        $this->comment('EQUITY PERIOD COMPREHENSIVE TEST');
        $this->comment('====================================');

        DB::beginTransaction();

        try {
            // Initialize services and base data
            $this->initializeServices();

            // Test Scenarios
            $this->comment("\n### SCENARIO 1: Two Equity Periods on the Same Day ###");
            $this->testTwoPeriodsOnSameDay();

            $this->comment("\n### SCENARIO 2: Equity Periods on Different Days ###");
            $this->testPeriodsDifferentDays();

            $this->comment("\n### SCENARIO 3: Edge Cases & Negative Tests ###");
            $this->testEdgeCases();

            DB::commit();
            $this->success("\n✅ ALL TESTS PASSED SUCCESSFULLY!");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("\n❌ TEST FAILED: " . $e->getMessage());
            $this->error("Stack Trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Initialize services and create base data
     */
    private function initializeServices(): void
    {
        $this->comment("Initializing services and base data...");

        $this->capitalService = app(CapitalService::class);
        $this->treasuryService = app(TreasuryService::class);

        // Get or create admin user
        $this->admin = User::where('email', 'admin@mawared.test')->first();
        if (!$this->admin) {
            $this->admin = User::factory()->create([
                'name' => 'Test Admin',
                'email' => 'admin@mawared.test',
            ]);
        }

        // Get or create treasury
        $this->treasury = Treasury::where('type', 'cash')->first();
        if (!$this->treasury) {
            $this->treasury = Treasury::create([
                'name' => 'Main Treasury',
                'type' => 'cash',
            ]);
        }

        $this->success("✓ Services initialized");
    }

    /**
     * Test Scenario 1: Equity period on the same day with two shareholders
     * - Partner 1 adds capital (auto-creates period 1)
     * - Partner 2 adds capital (updates period 1, does NOT auto-close)
     * - Verify percentages updated in period 1, capital, and treasury transactions
     * - Add revenues and expenses to period 1
     * - Manually close period 1 and verify profit allocation
     * - Verify period 2 is auto-created after manual close
     */
    private function testTwoPeriodsOnSameDay(): void
    {
        $this->comment("\n--- Starting Same Day Test ---");

        $testDate = Carbon::now()->startOfDay();

        // Clear previous test data
        $this->clearTestData();

        // Step 1: Create two shareholders
        $partner1 = Partner::create([
            'name' => 'Shareholder A',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        $partner2 = Partner::create([
            'name' => 'Shareholder B',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        $this->info("✓ Created partners: {$partner1->name}, {$partner2->name}");

        // Step 2: Partner 1 injects 60,000 capital (should auto-create Period 1)
        Carbon::setTestNow($testDate->copy()->addHours(9)); // 9:00 AM
        $this->capitalService->injectCapital($partner1, 60000, 'cash', [
            'treasury_id' => $this->treasury->id,
            'description' => 'Initial capital from Partner 1',
        ]);

        $partner1->refresh();
        $this->assertEquals(60000, $partner1->current_capital, 'Partner 1 capital should be 60,000');
        $this->assertEquals(100, $partner1->equity_percentage, 'Partner 1 should have 100% equity');

        $period1 = EquityPeriod::where('period_number', 1)->first();
        $this->assertNotNull($period1, 'Period 1 should be created');
        $this->assertEquals('open', $period1->status, 'Period 1 should be open');

        // Verify Period 1 partner lock
        $p1Lock = EquityPeriodPartner::where('equity_period_id', $period1->id)
            ->where('partner_id', $partner1->id)
            ->first();
        $this->assertNotNull($p1Lock, 'Partner 1 should be locked in Period 1');
        $this->assertEquals(100, $p1Lock->equity_percentage, 'Partner 1 should have 100% in Period 1');

        // Verify treasury transaction
        $tx1 = TreasuryTransaction::where('partner_id', $partner1->id)
            ->where('type', 'capital_deposit')
            ->first();
        $this->assertNotNull($tx1, 'Capital deposit transaction should exist for Partner 1');
        $this->assertEquals(60000, $tx1->amount, 'Transaction amount should be 60,000');

        $this->success("✓ Partner 1 capital injection verified (Period 1 created)");

        // Step 3: Partner 2 injects 40,000 capital on same day (should close Period 1, create Period 2)
        Carbon::setTestNow($testDate->copy()->addHours(14)); // 2:00 PM (same day, different time)
        $this->capitalService->injectCapital($partner2, 40000, 'cash', [
            'treasury_id' => $this->treasury->id,
            'description' => 'Initial capital from Partner 2',
        ]);

        $partner1->refresh();
        $partner2->refresh();

        // Verify capitals
        $this->assertEquals(60000, $partner1->current_capital, 'Partner 1 capital should remain 60,000');
        $this->assertEquals(40000, $partner2->current_capital, 'Partner 2 capital should be 40,000');

        // Verify percentages (60k + 40k = 100k total)
        $this->assertEquals(60, $partner1->equity_percentage, 'Partner 1 should have 60% equity');
        $this->assertEquals(40, $partner2->equity_percentage, 'Partner 2 should have 40% equity');

        // Verify Period 1 is still open (not auto-closed)
        $period1->refresh();
        $this->assertEquals('open', $period1->status, 'Period 1 should still be open');

        // Verify only Period 1 exists (no auto-creation of Period 2)
        $period2 = EquityPeriod::where('period_number', 2)->first();
        $this->assertNull($period2, 'Period 2 should NOT be auto-created');

        // Verify Period 1 now has both partners with updated percentages
        $p1LockPeriod1 = EquityPeriodPartner::where('equity_period_id', $period1->id)
            ->where('partner_id', $partner1->id)
            ->first();
        $p2LockPeriod1 = EquityPeriodPartner::where('equity_period_id', $period1->id)
            ->where('partner_id', $partner2->id)
            ->first();

        $this->assertNotNull($p1LockPeriod1, 'Partner 1 should be in Period 1');
        $this->assertNotNull($p2LockPeriod1, 'Partner 2 should be added to Period 1');
        $this->assertEquals(60, $p1LockPeriod1->equity_percentage, 'Partner 1 should have 60% in Period 1');
        $this->assertEquals(40, $p2LockPeriod1->equity_percentage, 'Partner 2 should have 40% in Period 1');
        $this->assertEquals(40000, $p2LockPeriod1->capital_at_start, 'Partner 2 capital_at_start should be 40,000');
        $this->assertEquals(40000, $p2LockPeriod1->capital_injected, 'Partner 2 capital_injected should be 40,000');

        $this->success("✓ Partner 2 capital injection verified (Period 1 updated, still open)");

        // Step 4: Add revenues and expenses in Period 1
        Carbon::setTestNow($testDate->copy()->addHours(15)); // 3:00 PM

        // Add revenues: 50,000
        $revenue1 = Revenue::create([
            'title' => 'Service Revenue',
            'description' => 'Consulting services',
            'amount' => 30000,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postRevenue($revenue1);

        $revenue2 = Revenue::create([
            'title' => 'Product Sales',
            'description' => 'Product sales revenue',
            'amount' => 20000,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postRevenue($revenue2);

        // Add expenses: 20,000
        $expense1 = Expense::create([
            'title' => 'Rent',
            'description' => 'Office rent',
            'amount' => 10000,
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postExpense($expense1);

        $expense2 = Expense::create([
            'title' => 'Utilities',
            'description' => 'Electricity and water',
            'amount' => 10000,
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postExpense($expense2);

        $this->success("✓ Added revenues (50,000) and expenses (20,000)");

        // Step 5: Manually close Period 1 and verify profit allocation
        Carbon::setTestNow($testDate->copy()->addHours(23)); // 11:00 PM (end of day)

        auth()->login($this->admin);
        $closedPeriod = $this->capitalService->closePeriodAndAllocate(
            now(),
            'End of day closing'
        );

        // Verify period calculations
        $closedPeriod->refresh();
        $this->assertEquals(1, $closedPeriod->period_number, 'Closed period should be Period 1');
        $this->assertEquals(50000, $closedPeriod->total_revenue, 'Total revenue should be 50,000');
        $this->assertEquals(20000, $closedPeriod->total_expenses, 'Total expenses should be 20,000');
        $this->assertEquals(30000, $closedPeriod->net_profit, 'Net profit should be 30,000');
        $this->assertEquals('closed', $closedPeriod->status, 'Period should be closed');

        // Verify Period 2 was auto-created after closing Period 1
        $period2AfterClose = EquityPeriod::where('period_number', 2)->first();
        $this->assertNotNull($period2AfterClose, 'Period 2 should be auto-created after closing Period 1');
        $this->assertEquals('open', $period2AfterClose->status, 'Period 2 should be open');

        // Verify profit allocation
        $partner1->refresh();
        $partner2->refresh();

        // Partner 1: 60% of 30,000 = 18,000
        // Partner 2: 40% of 30,000 = 12,000
        $expectedP1Capital = 60000 + 18000; // 78,000
        $expectedP2Capital = 40000 + 12000; // 52,000

        $this->assertEquals($expectedP1Capital, $partner1->current_capital, "Partner 1 capital should be {$expectedP1Capital}");
        $this->assertEquals($expectedP2Capital, $partner2->current_capital, "Partner 2 capital should be {$expectedP2Capital}");

        // Verify profit_allocated in equity_period_partners (Period 1)
        $p1LockPeriod1->refresh();
        $p2LockPeriod1->refresh();
        $this->assertEquals(18000, $p1LockPeriod1->profit_allocated, 'Partner 1 profit_allocated should be 18,000');
        $this->assertEquals(12000, $p2LockPeriod1->profit_allocated, 'Partner 2 profit_allocated should be 12,000');

        // Verify profit allocation treasury transactions exist
        $p1ProfitTx = TreasuryTransaction::where('partner_id', $partner1->id)
            ->where('type', 'profit_allocation')
            ->where('reference_id', $closedPeriod->id)
            ->first();
        $p2ProfitTx = TreasuryTransaction::where('partner_id', $partner2->id)
            ->where('type', 'profit_allocation')
            ->where('reference_id', $closedPeriod->id)
            ->first();

        $this->assertNotNull($p1ProfitTx, 'Partner 1 profit allocation transaction should exist');
        $this->assertNotNull($p2ProfitTx, 'Partner 2 profit allocation transaction should exist');
        $this->assertEquals(18000, $p1ProfitTx->amount, 'Partner 1 profit transaction should be 18,000');
        $this->assertEquals(12000, $p2ProfitTx->amount, 'Partner 2 profit transaction should be 12,000');

        // Verify treasury balance
        $treasuryBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
        // 60,000 (P1 capital) + 40,000 (P2 capital) + 50,000 (revenue) - 20,000 (expenses) + 30,000 (profit allocated) = 160,000
        // Note: Profit allocation creates treasury transactions that show where profits were allocated
        $this->assertEquals(160000, $treasuryBalance, 'Treasury balance should be 160,000');

        $this->success("✓ Period closed and profit allocated correctly");
        $this->success("✓✓ SCENARIO 1 PASSED: Two periods on same day");

        Carbon::setTestNow(); // Reset time
    }

    /**
     * Test Scenario 2: Equity periods on different days
     */
    private function testPeriodsDifferentDays(): void
    {
        $this->comment("\n--- Starting Different Days Test ---");

        // Clear previous test data
        $this->clearTestData();

        $day1 = Carbon::now()->startOfDay();
        $day2 = $day1->copy()->addDay();
        $day3 = $day2->copy()->addDay();

        // Day 1: Partner 1 adds capital
        Carbon::setTestNow($day1->copy()->addHours(10));

        $partner1 = Partner::create([
            'name' => 'Investor A',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        $this->capitalService->injectCapital($partner1, 100000, 'cash', [
            'treasury_id' => $this->treasury->id,
            'description' => 'Day 1: Initial investment',
        ]);

        $partner1->refresh();
        $this->assertEquals(100000, $partner1->current_capital);
        $this->assertEquals(100, $partner1->equity_percentage);

        $period1 = EquityPeriod::orderBy('period_number', 'desc')->first();
        $this->assertEquals(1, $period1->period_number);
        $this->assertEquals('open', $period1->status);

        $this->success("✓ Day 1: Partner 1 invested 100,000");

        // Day 1: Add some revenues and expenses
        $rev1 = Revenue::create([
            'title' => 'Day 1 Revenue',
            'amount' => 15000,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postRevenue($rev1);

        $exp1 = Expense::create([
            'title' => 'Day 1 Expense',
            'amount' => 5000,
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postExpense($exp1);

        // Day 2: Manually close Period 1, then add Partner 2
        Carbon::setTestNow($day2->copy()->addHours(9));

        // Close Period 1 manually
        auth()->login($this->admin);
        $closedPeriod1 = $this->capitalService->closePeriodAndAllocate(
            now(),
            'Day 2: Manual close of Period 1'
        );

        $closedPeriod1->refresh();
        $this->assertEquals(15000, $closedPeriod1->total_revenue, 'Period 1 revenue should be 15,000');
        $this->assertEquals(5000, $closedPeriod1->total_expenses, 'Period 1 expenses should be 5,000');
        $this->assertEquals(10000, $closedPeriod1->net_profit, 'Period 1 profit should be 10,000');

        // Verify Day 1 profit was allocated to Partner 1
        $partner1->refresh();

        // Period 1 profit: 15,000 - 5,000 = 10,000 (100% to Partner 1)
        $expectedP1AfterPeriod1 = 100000 + 10000; // 110,000

        $this->assertEquals($expectedP1AfterPeriod1, $partner1->current_capital, 'Partner 1 should have 110,000 after Period 1');

        // Now add Partner 2
        Carbon::setTestNow($day2->copy()->addHours(10));

        $partner2 = Partner::create([
            'name' => 'Investor B',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        // Inject capital for Partner 2 (creates/joins Period 2)
        $this->capitalService->injectCapital($partner2, 50000, 'cash', [
            'treasury_id' => $this->treasury->id,
            'description' => 'Day 2: Partner 2 investment',
        ]);

        $partner1->refresh();
        $partner2->refresh();

        $this->assertEquals(50000, $partner2->current_capital, 'Partner 2 should have 50,000');

        // New percentages: 110k + 50k = 160k total
        // P1: 110/160 = 68.75%, P2: 50/160 = 31.25%
        $this->assertEqualsWithDelta(68.75, $partner1->equity_percentage, 0.01, 'Partner 1 should have ~68.75%');
        $this->assertEqualsWithDelta(31.25, $partner2->equity_percentage, 0.01, 'Partner 2 should have ~31.25%');

        $this->success("✓ Day 2: Period 1 closed with 10,000 profit, Partner 2 invested 50,000");

        // Day 2: Add more revenues and expenses in Period 2
        $rev2 = Revenue::create([
            'title' => 'Day 2 Revenue',
            'amount' => 20000,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postRevenue($rev2);

        $exp2 = Expense::create([
            'title' => 'Day 2 Expense',
            'amount' => 8000,
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postExpense($exp2);

        // Day 3: Close period 2 and verify final balances
        Carbon::setTestNow($day3->copy()->addHours(10));

        auth()->login($this->admin);
        $closedPeriod2 = $this->capitalService->closePeriodAndAllocate(
            now(),
            'Day 3: End of period 2'
        );

        $closedPeriod2->refresh();
        $this->assertEquals(20000, $closedPeriod2->total_revenue, 'Period 2 revenue should be 20,000');
        $this->assertEquals(8000, $closedPeriod2->total_expenses, 'Period 2 expenses should be 8,000');
        $this->assertEquals(12000, $closedPeriod2->net_profit, 'Period 2 profit should be 12,000');

        $partner1->refresh();
        $partner2->refresh();

        // Period 2 profit: 12,000
        // P1: 68.75% of 12,000 = 8,250
        // P2: 31.25% of 12,000 = 3,750
        $expectedP1Final = 110000 + 8250; // 118,250
        $expectedP2Final = 50000 + 3750;  // 53,750

        $this->assertEqualsWithDelta($expectedP1Final, $partner1->current_capital, 1, "Partner 1 final capital should be ~{$expectedP1Final}");
        $this->assertEqualsWithDelta($expectedP2Final, $partner2->current_capital, 1, "Partner 2 final capital should be ~{$expectedP2Final}");

        // Verify treasury balance
        $treasuryBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
        // 100k (P1 day1) + 15k (rev1) - 5k (exp1) + 10k (period1 profit alloc) +
        // 50k (P2 day2) + 20k (rev2) - 8k (exp2) + 12k (period2 profit alloc) = 194k
        $this->assertEquals(194000, $treasuryBalance, 'Treasury balance should be 194,000');

        $this->success("✓✓ SCENARIO 2 PASSED: Multiple periods on different days");

        Carbon::setTestNow(); // Reset time
    }

    /**
     * Test Scenario 3: Edge cases and negative tests
     */
    private function testEdgeCases(): void
    {
        $this->comment("\n--- Starting Edge Cases Test ---");

        // Clear previous test data
        $this->clearTestData();

        // Edge Case 1: Zero profit period
        $this->comment("\nEdge Case 1: Zero profit period");

        $partner1 = Partner::create([
            'name' => 'Zero Profit Partner',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        $this->capitalService->injectCapital($partner1, 50000, 'cash', [
            'treasury_id' => $this->treasury->id,
        ]);

        // Add equal revenue and expenses (zero profit)
        $rev = Revenue::create([
            'title' => 'Break-even Revenue',
            'amount' => 10000,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postRevenue($rev);

        $exp = Expense::create([
            'title' => 'Break-even Expense',
            'amount' => 10000,
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postExpense($exp);

        auth()->login($this->admin);
        $period = $this->capitalService->closePeriodAndAllocate(now(), 'Zero profit test');

        $period->refresh();
        $this->assertEquals(0, $period->net_profit, 'Net profit should be 0');

        $partner1->refresh();
        $this->assertEquals(50000, $partner1->current_capital, 'Capital should remain 50,000 (no profit)');

        $this->success("✓ Edge Case 1 passed: Zero profit handled correctly");

        // Edge Case 2: Negative profit (loss) period
        $this->comment("\nEdge Case 2: Negative profit (loss)");
        $this->clearTestData();

        $partner2 = Partner::create([
            'name' => 'Loss Partner',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        $this->capitalService->injectCapital($partner2, 80000, 'cash', [
            'treasury_id' => $this->treasury->id,
        ]);

        // Add more expenses than revenue (loss)
        $rev2 = Revenue::create([
            'title' => 'Low Revenue',
            'amount' => 5000,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postRevenue($rev2);

        $exp2 = Expense::create([
            'title' => 'High Expense',
            'amount' => 15000,
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postExpense($exp2);

        auth()->login($this->admin);
        $lossPeriod = $this->capitalService->closePeriodAndAllocate(now(), 'Loss test');

        $lossPeriod->refresh();
        $this->assertEquals(-10000, $lossPeriod->net_profit, 'Net profit should be -10,000 (loss)');

        $partner2->refresh();
        // Capital should decrease by the loss: 80,000 - 10,000 = 70,000
        $this->assertEquals(70000, $partner2->current_capital, 'Capital should be 70,000 after loss');

        $this->success("✓ Edge Case 2 passed: Negative profit (loss) handled correctly");

        // Edge Case 3: Partner drawing before closing period
        $this->comment("\nEdge Case 3: Partner drawing");
        $this->clearTestData();

        $partner3 = Partner::create([
            'name' => 'Drawing Partner',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        $this->capitalService->injectCapital($partner3, 100000, 'cash', [
            'treasury_id' => $this->treasury->id,
        ]);

        // Partner takes drawing
        $this->capitalService->recordDrawing($partner3, 20000, 'Partner withdrawal');

        $partner3->refresh();
        $this->assertEquals(80000, $partner3->current_capital, 'Capital should be 80,000 after drawing');

        // Verify drawing transaction
        $drawingTx = TreasuryTransaction::where('partner_id', $partner3->id)
            ->where('type', 'partner_drawing')
            ->first();
        $this->assertNotNull($drawingTx, 'Drawing transaction should exist');
        $this->assertEquals(-20000, $drawingTx->amount, 'Drawing amount should be -20,000');

        // Verify in current period
        $currentPeriod = $this->capitalService->getCurrentPeriod();
        $partnerLock = EquityPeriodPartner::where('equity_period_id', $currentPeriod->id)
            ->where('partner_id', $partner3->id)
            ->first();
        $this->assertEquals(20000, $partnerLock->drawings_taken, 'drawings_taken should be 20,000');

        $this->success("✓ Edge Case 3 passed: Partner drawing recorded correctly");

        // Edge Case 4: Very small amounts (decimal precision)
        $this->comment("\nEdge Case 4: Decimal precision");
        $this->clearTestData();

        $partner4 = Partner::create([
            'name' => 'Decimal Partner 1',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        $partner5 = Partner::create([
            'name' => 'Decimal Partner 2',
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        // Inject odd amounts that will create decimal percentages
        $this->capitalService->injectCapital($partner4, 33333.33, 'cash', [
            'treasury_id' => $this->treasury->id,
        ]);

        $this->capitalService->injectCapital($partner5, 66666.67, 'cash', [
            'treasury_id' => $this->treasury->id,
        ]);

        $partner4->refresh();
        $partner5->refresh();

        // Total: 100,000
        // P4: 33333.33 / 100000 = 33.33333%
        // P5: 66666.67 / 100000 = 66.66667%
        $this->assertEqualsWithDelta(33.3333, $partner4->equity_percentage, 0.01, 'Partner 4 should have ~33.33%');
        $this->assertEqualsWithDelta(66.6667, $partner5->equity_percentage, 0.01, 'Partner 5 should have ~66.67%');

        // Add revenue with decimals
        $rev3 = Revenue::create([
            'title' => 'Decimal Revenue',
            'amount' => 999.99,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->admin->id,
        ]);
        $this->treasuryService->postRevenue($rev3);

        auth()->login($this->admin);
        $decimalPeriod = $this->capitalService->closePeriodAndAllocate(now(), 'Decimal test');

        $decimalPeriod->refresh();
        $partner4->refresh();
        $partner5->refresh();

        // Profit: 999.99
        // P4: 33.3333% of 999.99 ≈ 333.33
        // P5: 66.6667% of 999.99 ≈ 666.66
        $expectedP4 = 33333.33 + 333.33;
        $expectedP5 = 66666.67 + 666.66;

        $this->assertEqualsWithDelta($expectedP4, $partner4->current_capital, 1, "Partner 4 capital should be ~{$expectedP4}");
        $this->assertEqualsWithDelta($expectedP5, $partner5->current_capital, 1, "Partner 5 capital should be ~{$expectedP5}");

        $this->success("✓ Edge Case 4 passed: Decimal precision handled correctly");

        $this->success("✓✓ SCENARIO 3 PASSED: All edge cases handled correctly");

        Carbon::setTestNow(); // Reset time
    }

    /**
     * Clear test data for fresh test
     */
    private function clearTestData(): void
    {
        DB::table('equity_period_partners')->delete();
        DB::table('equity_periods')->delete();
        DB::table('treasury_transactions')->delete();
        DB::table('revenues')->delete();
        DB::table('expenses')->delete();
        DB::table('partners')->where('type', 'shareholder')->delete();
    }

    /**
     * Custom assertion for delta comparison
     */
    private function assertEqualsWithDelta($expected, $actual, $delta, $message = '')
    {
        $diff = abs($expected - $actual);
        if ($diff > $delta) {
            throw new \Exception(
                ($message ? $message . ': ' : '') .
                "Expected {$expected}, got {$actual}, difference {$diff} exceeds delta {$delta}"
            );
        }
    }

    /**
     * Custom assertion
     */
    private function assertEquals($expected, $actual, $message = '')
    {
        if ($expected != $actual) {
            throw new \Exception(
                ($message ? $message . ': ' : '') .
                "Expected {$expected}, got {$actual}"
            );
        }
    }

    /**
     * Custom assertion for not null
     */
    private function assertNotNull($value, $message = '')
    {
        if ($value === null) {
            throw new \Exception($message ?: 'Value is null');
        }
    }

    /**
     * Custom assertion for null
     */
    private function assertNull($value, $message = '')
    {
        if ($value !== null) {
            throw new \Exception($message ?: 'Value is not null');
        }
    }

    /**
     * Output helpers
     */
    private function comment($message)
    {
        echo "\n\033[33m{$message}\033[0m"; // Yellow
    }

    private function info($message)
    {
        echo "\n  {$message}";
    }

    private function success($message)
    {
        echo "\n\033[32m{$message}\033[0m"; // Green
    }

    private function error($message)
    {
        echo "\n\033[31m{$message}\033[0m"; // Red
    }
}
