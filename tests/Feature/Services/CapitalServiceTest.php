<?php

namespace Tests\Feature\Services;

use App\Enums\TransactionType;
use App\Models\Expense;
use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
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

    public function test_calculates_financial_summary_with_cogs_correctly(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 10000, 'equity_percentage' => 100]);
        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partner]);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // 1. Revenue: Sales Invoice (1000) with COGS (400)
        // Using COGS-based accounting: cost_total represents the cost of goods sold
        $salesInvoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1000,
            'cost_total' => 400, // COGS - Cost of Goods Sold
            'created_at' => now(),
        ]);

        // 2. Expense: Operating Expense (100)
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
        // COGS = 400
        // Operating Expenses = 100
        // Total Expenses = 400 + 100 = 500
        // Gross Profit = 1000 - 400 = 600
        // Net Profit = 1000 - 500 = 500
        $this->assertEquals(1000, $summary['total_revenue']);
        $this->assertEquals(400, $summary['cogs']);
        $this->assertEquals(100, $summary['operating_expenses']);
        $this->assertEquals(500, $summary['total_expenses']);
        $this->assertEquals(600, $summary['gross_profit']);
        $this->assertEquals(500, $summary['net_profit']);
    }

    public function test_financial_summary_includes_commissions(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 10000, 'equity_percentage' => 100]);
        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partner]);

        // Sales Invoice with COGS
        SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1000,
            'cost_total' => 400,
            'created_at' => now(),
        ]);

        // Commission Payout (50) - stored as negative in treasury
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => TransactionType::COMMISSION_PAYOUT->value,
            'amount' => '-50.00',
            'description' => 'Commission payout',
            'created_at' => now(),
        ]);

        // ACT
        $summary = $this->capitalService->getFinancialSummary($period);

        // ASSERT
        // Revenue = 1000
        // COGS = 400
        // Commissions = 50
        // Total Expenses = 400 + 50 = 450
        // Net Profit = 1000 - 450 = 550
        $this->assertEquals(50, $summary['commissions']);
        $this->assertEquals(450, $summary['total_expenses']);
        $this->assertEquals(550, $summary['net_profit']);
    }

    public function test_financial_summary_uses_net_cogs_after_returns(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 10000, 'equity_percentage' => 100]);
        $customer = Partner::factory()->create(['type' => 'customer']);
        $warehouse = Warehouse::factory()->create();
        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partner]);

        // Sales Invoice with GROSS COGS = 500 (unchanged by returns)
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1000,
            'cost_total' => 500, // GROSS COGS - never modified
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'created_at' => now(),
        ]);

        // Sales Return with cost_total = 200 (cost of returned goods)
        // Net COGS = 500 - 200 = 300
        SalesReturn::factory()->create([
            'status' => 'posted',
            'total' => 400,
            'cost_total' => 200, // Cost of returned items
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'sales_invoice_id' => $invoice->id,
            'created_at' => now(),
        ]);

        // ACT
        $summary = $this->capitalService->getFinancialSummary($period);

        // ASSERT
        // Net COGS = Gross COGS (500) - Returned COGS (200) = 300
        $this->assertEquals(300, $summary['cogs']);
        $this->assertEquals(700, $summary['gross_profit']); // 1000 - 300
    }

    public function test_financial_summary_includes_discount_allowed(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 10000, 'equity_percentage' => 100]);
        $customer = Partner::factory()->create(['type' => 'customer']);
        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partner]);

        $salesInvoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1000,
            'cost_total' => 400,
            'partner_id' => $customer->id,
            'created_at' => now(),
        ]);

        // Settlement discount given at payment time
        // Use 'sales_invoice' morph alias for consistency with morph map
        InvoicePayment::create([
            'payable_type' => 'sales_invoice',
            'payable_id' => $salesInvoice->id,
            'amount' => 950,
            'discount' => 50, // Discount allowed at payment time
            'payment_date' => now(),
            'partner_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        // ACT
        $summary = $this->capitalService->getFinancialSummary($period);

        // ASSERT
        $this->assertEquals(50, $summary['discount_allowed']);
        // Total Expenses = COGS(400) + Discount(50) = 450
        $this->assertEquals(450, $summary['total_expenses']);
    }

    public function test_financial_summary_includes_salaries_and_depreciation(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 10000, 'equity_percentage' => 100]);
        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partner]);

        // Sales Invoice
        SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 2000,
            'cost_total' => 800,
            'created_at' => now(),
        ]);

        // Salary payment (stored as negative in treasury)
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => TransactionType::SALARY_PAYMENT->value,
            'amount' => '-300.00',
            'description' => 'Employee salary',
            'created_at' => now(),
        ]);

        // Depreciation expense - now stored as Expense record (NON-CASH, not treasury transaction)
        Expense::create([
            'title' => 'Asset depreciation',
            'amount' => '100.00',
            'expense_date' => now(),
            'is_non_cash' => true,
            'fixed_asset_id' => \App\Models\FixedAsset::factory()->create()->id,
            'treasury_id' => null, // Non-cash expense has no treasury
        ]);

        // ACT
        $summary = $this->capitalService->getFinancialSummary($period);

        // ASSERT
        $this->assertEquals(300, $summary['manager_salaries']);
        $this->assertEquals(100, $summary['depreciation']);
        // Total Expenses = COGS(800) + Salaries(300) + Depreciation(100) = 1200
        $this->assertEquals(1200, $summary['total_expenses']);
        // Net Profit = 2000 - 1200 = 800
        $this->assertEquals(800, $summary['net_profit']);
    }

    public function test_financial_summary_includes_other_revenue(): void
    {
        // ARRANGE
        $partner = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 10000, 'equity_percentage' => 100]);
        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partner]);

        // Sales Invoice
        SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1000,
            'cost_total' => 400,
            'created_at' => now(),
        ]);

        // Other Revenue (e.g., interest income)
        Revenue::create([
            'title' => 'Interest Income',
            'amount' => 200,
            'treasury_id' => $this->treasury->id,
            'revenue_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // ACT
        $summary = $this->capitalService->getFinancialSummary($period);

        // ASSERT
        $this->assertEquals(1000, $summary['sales_revenue']);
        $this->assertEquals(200, $summary['other_revenue']);
        // Total Revenue = Sales(1000) + Other(200) = 1200
        $this->assertEquals(1200, $summary['total_revenue']);
        // Net Profit = 1200 - 400 = 800
        $this->assertEquals(800, $summary['net_profit']);
    }

    public function test_closes_period_and_allocates_profit(): void
    {
        // ARRANGE
        // Partner A: 60%, Partner B: 40%
        $partnerA = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 6000, 'equity_percentage' => 60]);
        $partnerB = Partner::factory()->create(['type' => 'shareholder', 'current_capital' => 4000, 'equity_percentage' => 40]);

        $period = $this->capitalService->createInitialPeriod(now()->subMonth(), [$partnerA, $partnerB]);

        // Generate 1000 Net Profit
        // Sales: 1500, COGS: 500
        // Net Profit = 1500 - 500 = 1000
        SalesInvoice::factory()->create([
            'status' => 'posted',
            'total' => 1500,
            'cost_total' => 500, // COGS
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
