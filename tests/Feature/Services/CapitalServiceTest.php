<?php

namespace Tests\Feature\Services;

use App\Enums\TransactionType;
use App\Models\Expense;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CapitalService;
use App\Services\StockService;
use App\Services\TreasuryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CapitalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CapitalService $capitalService;

    protected TreasuryService $treasuryService;

    protected StockService $stockService;

    protected Treasury $treasury;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treasuryService = new TreasuryService;
        $this->stockService = new StockService;
        $this->capitalService = new CapitalService($this->treasuryService);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create a main treasury for cash transactions
        $this->treasury = Treasury::factory()->create([
            'type' => 'cash',
            'name' => 'Main Treasury',
        ]);

        // Seed initial balance to allow withdrawals/expenses
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'income',
            'amount' => '1000000.00',
            'description' => 'Initial Seed',
        ]);
    }

    public function test_creates_initial_period_correctly(): void
    {
        // ARRANGE
        $partner1 = Partner::factory()->create([
            'type' => 'shareholder',
            'current_capital' => 50000,
            'equity_percentage' => 50,
        ]);

        $partner2 = Partner::factory()->create([
            'type' => 'shareholder',
            'current_capital' => 50000,
            'equity_percentage' => 50,
        ]);

        $startDate = Carbon::now()->startOfYear();

        // ACT
        $period = $this->capitalService->createInitialPeriod($startDate, [$partner1, $partner2]);

        // ASSERT
        $this->assertDatabaseHas('equity_periods', [
            'id' => $period->id,
            'status' => 'open',
            'period_number' => 1,
        ]);

        $this->assertDatabaseHas('equity_period_partners', [
            'equity_period_id' => $period->id,
            'partner_id' => $partner1->id,
            'equity_percentage' => 50,
            'capital_at_start' => 50000,
        ]);

        $this->assertDatabaseHas('equity_period_partners', [
            'equity_period_id' => $period->id,
            'partner_id' => $partner2->id,
            'equity_percentage' => 50,
            'capital_at_start' => 50000,
        ]);
    }

    public function test_injects_capital_increases_balance_and_recalculates_percentages(): void
    {
        // ARRANGE
        // Initial setup: Partner A has 1000 (100%)
        $partnerA = Partner::factory()->create([
            'type' => 'shareholder',
            'current_capital' => 1000,
            'equity_percentage' => 100,
        ]);

        // Partner B joins with 0 initially
        $partnerB = Partner::factory()->create([
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        // Create initial period
        $this->capitalService->createInitialPeriod(now(), [$partnerA]);

        // ACT
        // Partner B injects 1000. Now total is 2000. A=1000(50%), B=1000(50%)
        $this->capitalService->injectCapital($partnerB, 1000);

        // ASSERT
        $partnerA->refresh();
        $partnerB->refresh();

        $this->assertEquals(1000, $partnerA->current_capital);
        $this->assertEquals(1000, $partnerB->current_capital);

        $this->assertEquals(50, $partnerA->equity_percentage);
        $this->assertEquals(50, $partnerB->equity_percentage);

        // Verify transaction recorded
        $this->assertDatabaseHas('treasury_transactions', [
            'type' => TransactionType::CAPITAL_DEPOSIT->value,
            'amount' => '1000.0000',
            'partner_id' => $partnerB->id,
        ]);

        // Verify current period updated
        $period = $this->capitalService->getCurrentPeriod();
        $this->assertDatabaseHas('equity_period_partners', [
            'equity_period_id' => $period->id,
            'partner_id' => $partnerB->id,
            'equity_percentage' => 50,
            'capital_injected' => 1000,
        ]);
    }

    public function test_records_drawings_decreases_balance(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create([
            'type' => 'shareholder',
            'current_capital' => 10000,
            'equity_percentage' => 100,
        ]);

        $this->capitalService->createInitialPeriod(now(), [$partner]);

        // ACT
        $this->capitalService->recordDrawing($partner, 500, 'Personal use');

        // ASSERT
        $partner->refresh();
        $this->assertEquals(9500, $partner->current_capital);

        // Verify transaction
        $this->assertDatabaseHas('treasury_transactions', [
            'type' => TransactionType::PARTNER_DRAWING->value,
            'amount' => '-500.0000',
            'partner_id' => $partner->id,
        ]);

        // Verify period record
        $period = $this->capitalService->getCurrentPeriod();
        $this->assertDatabaseHas('equity_period_partners', [
            'equity_period_id' => $period->id,
            'partner_id' => $partner->id,
            'drawings_taken' => 500,
        ]);
    }

    public function test_calculates_financial_summary_correctly(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 10000, 'equity_percentage' => 100]);
        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partner]);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // 1. Revenue: Sales Invoice (1000)
        $salesInvoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1000,
            'created_at' => now(),
        ]);

        // 2. Expense: Purchase Invoice (400)
        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'status' => 'posted',
            'total' => 400,
            'created_at' => now(),
        ]);

        // 3. Expense: Operating Expense (100)
        Expense::create([
            'title' => 'Rent',
            'amount' => 100,
            'treasury_id' => $this->treasury->id,
            'expense_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // ACT
        $summary = $this->capitalService->getFinancialSummary($period);

        // ASSERT
        // Revenue = 1000
        // Expenses = 400 + 100 = 500
        // Net Profit = 1000 - 500 = 500
        $this->assertEquals(1000, $summary['total_revenue']);
        $this->assertEquals(500, $summary['total_expenses']);
        $this->assertEquals(500, $summary['net_profit']);
    }

    public function test_closes_period_and_allocates_profit(): void
    {
        // ARRANGE
        // Partner A: 60%, Partner B: 40%
        $partnerA = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 6000, 'equity_percentage' => 60]);
        $partnerB = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 4000, 'equity_percentage' => 40]);

        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partnerA, $partnerB]);

        // Generate 1000 Profit
        // Sales: 1000
        SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1000,
            'created_at' => now(),
        ]);

        // ACT
        $closedPeriod = $this->capitalService->closePeriodAndAllocate(now(), 'End of Year');

        // ASSERT
        // Period status closed
        $this->assertEquals('closed', $closedPeriod->status);
        $this->assertEquals(1000, $closedPeriod->net_profit);

        // Profit Allocation
        // Partner A: 60% of 1000 = 600. New Capital = 6000 + 600 = 6600
        // Partner B: 40% of 1000 = 400. New Capital = 4000 + 400 = 4400
        $partnerA->refresh();
        $partnerB->refresh();

        $this->assertEquals(6600, $partnerA->current_capital);
        $this->assertEquals(4400, $partnerB->current_capital);

        // Transactions recorded
        $this->assertDatabaseHas('treasury_transactions', [
            'type' => TransactionType::PROFIT_ALLOCATION->value,
            'partner_id' => $partnerA->id,
            'amount' => '600.0000',
        ]);
        $this->assertDatabaseHas('treasury_transactions', [
            'type' => TransactionType::PROFIT_ALLOCATION->value,
            'partner_id' => $partnerB->id,
            'amount' => '400.0000',
        ]);

        // New period created
        $newPeriod = $this->capitalService->getCurrentPeriod();
        $this->assertNotNull($newPeriod);
        $this->assertEquals($period->period_number + 1, $newPeriod->period_number);
        $this->assertEquals('open', $newPeriod->status);
    }

    public function test_throws_exception_if_total_capital_is_zero_when_calculating_percentages(): void
    {
        // ARRANGE
        // Partner with 0 capital
        Partner::factory()->create([
            'type' => 'shareholder',
            'current_capital' => 0,
            'equity_percentage' => 0,
        ]);

        // ACT & ASSERT
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Total capital must be positive');

        $this->capitalService->recalculateEquityPercentages();
    }

    public function test_get_partner_capital_ledger_returns_correct_transactions(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create([
            'type' => 'shareholder',
            'current_capital' => 1000,
            'equity_percentage' => 100,
        ]);

        $this->capitalService->createInitialPeriod(now(), [$partner]);

        // 1. Inject Capital (+1000)
        $this->capitalService->injectCapital($partner, 1000);

        // 2. Drawing (-200)
        $this->capitalService->recordDrawing($partner, 200);

        // ACT
        $ledger = $this->capitalService->getPartnerCapitalLedger($partner);

        // ASSERT
        $this->assertCount(2, $ledger);

        // Latest first
        $this->assertEquals(TransactionType::PARTNER_DRAWING->value, $ledger->first()->type);
        $this->assertEquals('-200.0000', $ledger->first()->amount);

        $this->assertEquals(TransactionType::CAPITAL_DEPOSIT->value, $ledger->last()->type);
        $this->assertEquals('1000.0000', $ledger->last()->amount);
    }
}
