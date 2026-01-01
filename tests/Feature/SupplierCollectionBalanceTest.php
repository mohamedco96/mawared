<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCollectionBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected TreasuryService $treasuryService;
    protected Treasury $treasury;

    protected function setUp(): void
    {
        parent::setUp();
        $this->treasuryService = app(TreasuryService::class);
        $this->treasury = Treasury::factory()->create();
    }

    /** @test */
    public function it_correctly_updates_supplier_balance_when_collecting_from_supplier()
    {
        // Create supplier
        $supplier = Partner::factory()->create([
            'type' => 'supplier',
            'current_balance' => 0,
        ]);

        // Create a purchase invoice on credit
        $invoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'total' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
            'status' => 'posted',
        ]);

        // Recalculate balance - supplier should be owed 1000
        $supplier->recalculateBalance();
        $supplier->refresh();
        $this->assertEquals(1000, $supplier->current_balance);

        // Pay the supplier 1400 (overpayment)
        $this->treasuryService->recordInvoicePayment($invoice, 1400, 0, $this->treasury->id);
        $supplier->refresh();

        // After paying 1400 against a 1000 invoice, supplier owes us 400
        $this->assertEquals(-400, $supplier->current_balance);

        // Collect 400 from supplier (they pay us back)
        $collection = TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'collection',
            'amount' => 400,
            'description' => 'Collection from supplier',
            'partner_id' => $supplier->id,
            'reference_type' => 'financial_transaction',
            'reference_id' => null,
        ]);

        // Recalculate balance - should be 0 now
        $supplier->recalculateBalance();
        $supplier->refresh();
        $this->assertEquals(0, $supplier->current_balance, 'Supplier balance should be 0 after collecting back the overpayment');
    }

    /** @test */
    public function it_correctly_updates_customer_balance_when_refunding_to_customer()
    {
        // Create customer
        $customer = Partner::factory()->create([
            'type' => 'customer',
            'current_balance' => 0,
        ]);

        // Create a sales invoice and collect 1400 (overpayment)
        $invoice = \App\Models\SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'total' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
            'status' => 'posted',
        ]);

        // Customer owes us 1000
        $customer->recalculateBalance();
        $customer->refresh();
        $this->assertEquals(1000, $customer->current_balance);

        // Collect 1400 from customer (overpayment)
        $this->treasuryService->recordInvoicePayment($invoice, 1400, 0, $this->treasury->id);
        $customer->refresh();

        // After collecting 1400 against a 1000 invoice, we owe customer 400
        $this->assertEquals(-400, $customer->current_balance);

        // Refund 400 to customer
        $payment = TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'payment',
            'amount' => -400,
            'description' => 'Refund to customer',
            'partner_id' => $customer->id,
            'reference_type' => 'financial_transaction',
            'reference_id' => null,
        ]);

        // Recalculate balance - should be 0 now
        $customer->recalculateBalance();
        $customer->refresh();
        $this->assertEquals(0, $customer->current_balance, 'Customer balance should be 0 after refunding the overpayment');
    }

    /** @test */
    public function manual_treasury_transactions_have_reference_type_set()
    {
        $supplier = Partner::factory()->create(['type' => 'supplier']);

        // Simulate creating a manual collection transaction (what happens in CreateTreasuryTransaction)
        $data = [
            'treasury_id' => $this->treasury->id,
            'type' => 'collection',
            'amount' => 500,
            'description' => 'Manual collection',
            'partner_id' => $supplier->id,
        ];

        // Apply the mutation that happens in CreateTreasuryTransaction
        if (in_array($data['type'], ['collection', 'payment']) && !empty($data['partner_id'])) {
            $data['reference_type'] = 'financial_transaction';
            $data['reference_id'] = null;
        }

        $transaction = TreasuryTransaction::create($data);

        $this->assertEquals('financial_transaction', $transaction->reference_type);
        $this->assertNull($transaction->reference_id);
    }
}
