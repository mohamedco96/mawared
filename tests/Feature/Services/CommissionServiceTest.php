<?php

namespace Tests\Feature\Services;

use App\Enums\TransactionType;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CommissionService;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CommissionService $commissionService;
    protected TreasuryService $treasuryService;
    protected User $salesperson;
    protected Treasury $treasury;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treasuryService = new TreasuryService();
        $this->commissionService = new CommissionService($this->treasuryService);

        $this->salesperson = User::factory()->create(['name' => 'Sales Person']);
        
        // Create treasury for payouts
        $this->treasury = Treasury::factory()->create([
            'type' => 'cash',
            'name' => 'Main Treasury'
        ]);

        // Seed treasury
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'income',
            'amount' => '10000.00',
            'description' => 'Seed'
        ]);
    }

    public function test_calculates_commission_amount_on_invoice_creation(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'total' => 1000,
            'sales_person_id' => $this->salesperson->id,
            'commission_rate' => 5.00, // 5%
            'status' => 'draft',
            'commission_amount' => 0
        ]);

        // ACT
        $this->commissionService->calculateCommission($invoice);

        // ASSERT
        // 5% of 1000 = 50
        $this->assertEquals(50, $invoice->commission_amount);
    }

    public function test_sets_commission_to_zero_if_no_salesperson_or_rate(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'total' => 1000,
            'sales_person_id' => null,
            'commission_rate' => 5.00,
            'status' => 'draft'
        ]);

        // ACT
        $this->commissionService->calculateCommission($invoice);

        // ASSERT
        $this->assertEquals(0, $invoice->commission_amount);
    }

    public function test_pays_commission_records_transaction_and_updates_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'total' => 1000,
            'sales_person_id' => $this->salesperson->id,
            'commission_rate' => 10.00,
            'commission_amount' => 100, // Pre-calculated
            'status' => 'posted', // Must be posted
            'commission_paid' => false
        ]);

        // ACT
        $this->commissionService->payCommission($invoice, $this->treasury->id);

        // ASSERT
        $invoice->refresh();
        $this->assertTrue($invoice->commission_paid);

        $this->assertDatabaseHas('treasury_transactions', [
            'type' => TransactionType::COMMISSION_PAYOUT->value,
            'amount' => '-100.0000', // Negative payout
            'treasury_id' => $this->treasury->id,
            'reference_type' => 'sales_invoice',
            'reference_id' => $invoice->id
        ]);
    }

    public function test_throws_exception_if_paying_unposted_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
            'sales_person_id' => $this->salesperson->id,
            'commission_amount' => 100
        ]);

        // ACT & ASSERT
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن دفع عمولة لفاتورة غير مؤكدة');

        $this->commissionService->payCommission($invoice, $this->treasury->id);
    }

    public function test_throws_exception_if_commission_already_paid(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'sales_person_id' => $this->salesperson->id,
            'commission_amount' => 100,
            'commission_paid' => true
        ]);

        // ACT & ASSERT
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('تم دفع العمولة مسبقاً');

        $this->commissionService->payCommission($invoice, $this->treasury->id);
    }

    public function test_reverses_commission_proportionally_on_return(): void
    {
        // ARRANGE
        // Original Sale: 1000 Total, 10% Comm = 100 Comm. Paid.
        $invoice = SalesInvoice::factory()->create([
            'total' => 1000,
            'sales_person_id' => $this->salesperson->id,
            'commission_rate' => 10.00,
            'commission_amount' => 100,
            'status' => 'posted',
            'commission_paid' => true,
            'invoice_number' => 'INV-001'
        ]);

        // Return: 500 Total (50% of sale)
        $return = SalesReturn::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'total' => 500,
            'status' => 'posted',
            'return_number' => 'RET-001'
        ]);

        // ACT
        $this->commissionService->reverseCommission($return, $this->treasury->id);

        // ASSERT
        // Should reverse 50% of commission (50)
        // New commission on invoice should be 100 - 50 = 50
        $invoice->refresh();
        $this->assertEquals(50, $invoice->commission_amount);
        $this->assertTrue((bool)$invoice->commission_paid); // Still partially paid

        // Verify Reversal Transaction (Positive income to treasury)
        $this->assertDatabaseHas('treasury_transactions', [
            'type' => TransactionType::COMMISSION_REVERSAL->value,
            'amount' => '50.0000',
            'treasury_id' => $this->treasury->id,
            'reference_type' => 'sales_return',
            'reference_id' => $return->id
        ]);
    }

    public function test_generates_salesperson_report_correctly(): void
    {
        // ARRANGE
        // Invoice 1: 1000, Comm 100 (Paid)
        SalesInvoice::factory()->create([
            'sales_person_id' => $this->salesperson->id,
            'total' => 1000,
            'commission_amount' => 100,
            'commission_paid' => true,
            'status' => 'posted',
            'created_at' => now()
        ]);

        // Invoice 2: 2000, Comm 200 (Unpaid)
        SalesInvoice::factory()->create([
            'sales_person_id' => $this->salesperson->id,
            'total' => 2000,
            'commission_amount' => 200,
            'commission_paid' => false,
            'status' => 'posted',
            'created_at' => now()
        ]);

        // Invoice 3: Other user (should be ignored)
        SalesInvoice::factory()->create([
            'sales_person_id' => User::factory()->create()->id,
            'total' => 500,
            'status' => 'posted'
        ]);

        // ACT
        $report = $this->commissionService->getSalespersonReport(
            $this->salesperson, 
            now()->startOfMonth(), 
            now()->endOfMonth()
        );

        // ASSERT
        $this->assertEquals(3000, $report['total_sales']);       // 1000 + 2000
        $this->assertEquals(300, $report['total_commission']);   // 100 + 200
        $this->assertEquals(100, $report['paid_commission']);    // 100
        $this->assertEquals(200, $report['unpaid_commission']);  // 200
        $this->assertEquals(2, $report['invoices_count']);
    }
}
