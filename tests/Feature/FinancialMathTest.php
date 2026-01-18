<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialMathTest extends TestCase
{
    use RefreshDatabase;

    private TreasuryService $treasuryService;
    private StockService $stockService;
    private Treasury $treasury;
    private Warehouse $warehouse;
    private Partner $customer;
    private Partner $supplier;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->treasuryService = new TreasuryService();
        $this->stockService = new StockService();

        // Create test data
        $this->treasury = Treasury::create([
            'name' => 'Test Treasury',
            'type' => 'cash',
        ]);

        // Inject initial capital
        \App\Models\TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'income',
            'amount' => 1000000,
            'description' => 'Initial Capital for Testing',
            'reference_type' => 'capital_injection',
            'reference_id' => Str::ulid(),
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
        ]);

        $this->customer = Partner::create([
            'name' => 'Test Customer',
            'type' => 'customer',
        ]);

        $this->supplier = Partner::create([
            'name' => 'Test Supplier',
            'type' => 'supplier',
        ]);

        // Create product using factory
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
        ]);
    }

    /** @test */
    public function test_partial_payment_purchase_invoice_creates_correct_balances()
    {
        // Scenario: Purchase invoice total 400, discount 4 (net 396), paid 200
        // Expected: Partner balance = -196 (we owe supplier), Treasury = -200

        $invoice = PurchaseInvoice::create([
            'invoice_number' => 'PI-001',
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->supplier->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'discount_type' => 'fixed',
            'discount_value' => 4,
            'subtotal' => 400,
            'discount' => 4,
            'total' => 396,
            'paid_amount' => 200,
            'remaining_amount' => 196,
        ]);

        // Add items
        $invoice->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 4,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 400,
        ]);

        // Post the invoice
        $this->stockService->postPurchaseInvoice($invoice);
        $this->treasuryService->postPurchaseInvoice($invoice, $this->treasury->id);
        $invoice->update(['status' => 'posted']);

        // Recalculate partner balance after status update
        $this->supplier->recalculateBalance();

        // Assertions
        $this->assertEquals(999800, $this->treasuryService->getTreasuryBalance($this->treasury->id)); // 1,000,000 - 200

        $this->supplier->refresh();
        $this->assertEquals(196, round($this->supplier->current_balance, 2));

        // Verify only ONE treasury transaction was created for this invoice
        $this->assertEquals(1, $invoice->treasuryTransactions()->count());
    }

    /** @test */
    public function test_full_payment_invoice_zeros_partner_balance()
    {
        // Scenario: Sales invoice total 1000, paid 1000
        // Expected: Partner balance = 0, Treasury = +1000

        // Add stock before sales
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $invoice = SalesInvoice::create([
            'invoice_number' => 'SI-001',
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'paid_amount' => 1000,
            'remaining_amount' => 0,
        ]);

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        // Post the invoice
        $this->stockService->postSalesInvoice($invoice);
        $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
        $invoice->update(['status' => 'posted']);

        // Recalculate partner balance after status update
        $this->customer->recalculateBalance();

        // Assertions
        $this->assertEquals(1001000, $this->treasuryService->getTreasuryBalance($this->treasury->id)); // 1,000,000 + 1000

        $this->customer->refresh();
        $this->assertEquals(0, round($this->customer->current_balance, 2));
    }

    /** @test */
    public function test_credit_invoice_with_subsequent_payment()
    {
        // Scenario: Sales invoice total 500, paid 0 (full credit), then pay 300 later
        // Expected: After posting = +500 balance, Treasury = 1,000,000 (no change)
        // Expected: After payment = +200 balance, Treasury = 1,000,300

        // Add stock before sales
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $invoice = SalesInvoice::create([
            'invoice_number' => 'SI-002',
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'subtotal' => 500,
            'discount' => 0,
            'total' => 500,
            'paid_amount' => 0,
            'remaining_amount' => 500,
        ]);

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 500,
        ]);

        // Post the invoice
        $this->stockService->postSalesInvoice($invoice);
        $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
        $invoice->update(['status' => 'posted']);

        // Recalculate partner balance after status update
        $this->customer->recalculateBalance();

        // Check balance after posting
        $this->customer->refresh();
        $this->assertEquals(500, round($this->customer->current_balance, 2));
        $this->assertEquals(1000000, $this->treasuryService->getTreasuryBalance($this->treasury->id)); // No change - credit invoice

        // Make a payment
        $payment = $this->treasuryService->recordInvoicePayment(
            $invoice,
            300,
            0,
            $this->treasury->id,
            'Partial payment'
        );

        // Check balance after payment
        $this->customer->refresh();
        $this->assertEquals(200, round($this->customer->current_balance, 2));
        $this->assertEquals(1000300, $this->treasuryService->getTreasuryBalance($this->treasury->id)); // 1,000,000 + 300

        // Verify payment record
        $this->assertNotNull($payment);
        $this->assertEquals(300, $payment->amount);
    }

    /** @test */
    public function test_sales_return_reduces_customer_balance()
    {
        // Scenario: Sales invoice 1000 (cash), then return 200 (cash)
        // Expected: Partner balance = 0 (cash transactions don't affect balance), Treasury = +800

        // Add stock before sales
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $invoice = SalesInvoice::create([
            'invoice_number' => 'SI-003',
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'paid_amount' => 1000,
            'remaining_amount' => 0,
        ]);

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        // Post the invoice
        $this->stockService->postSalesInvoice($invoice);
        $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
        $invoice->update(['status' => 'posted']);

        // Recalculate partner balance after status update
        $this->customer->recalculateBalance();

        // Create return
        $return = SalesReturn::create([
            'return_number' => 'SR-001',
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'subtotal' => 200,
            'discount' => 0,
            'total' => 200,
        ]);

        $return->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 200,
        ]);

        // Post the return
        $this->stockService->postSalesReturn($return);
        $this->treasuryService->postSalesReturn($return, $this->treasury->id);
        $return->update(['status' => 'posted']);

        // Recalculate partner balance after status update
        $this->customer->recalculateBalance();

        // Assertions
        $this->assertEquals(1000800, $this->treasuryService->getTreasuryBalance($this->treasury->id)); // 1,000,000 + 1000 - 200

        $this->customer->refresh();
        $this->assertEquals(0, round($this->customer->current_balance, 2)); // Cash transactions don't affect balance
    }

    /** @test */
    public function test_multiple_partial_payments_on_same_invoice()
    {
        // Scenario: Invoice 1000 credit, pay 300, then 400, then 300
        // Expected: After all payments, balance = 0, treasury = 1000

        // Add stock before sales
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $invoice = SalesInvoice::create([
            'invoice_number' => 'SI-004',
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
        ]);

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        // Post the invoice
        $this->stockService->postSalesInvoice($invoice);
        $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
        $invoice->update(['status' => 'posted']);

        // Recalculate partner balance after status update
        $this->customer->recalculateBalance();

        // Payment 1
        $this->treasuryService->recordInvoicePayment($invoice, 300, 0, $this->treasury->id);
        $this->customer->refresh();
        $this->assertEquals(700, round($this->customer->current_balance, 2));

        // Payment 2
        $this->treasuryService->recordInvoicePayment($invoice, 400, 0, $this->treasury->id);
        $this->customer->refresh();
        $this->assertEquals(300, round($this->customer->current_balance, 2));

        // Payment 3
        $this->treasuryService->recordInvoicePayment($invoice, 300, 0, $this->treasury->id);
        $this->customer->refresh();
        $this->assertEquals(0, round($this->customer->current_balance, 2));

        // Final treasury balance
        $this->assertEquals(1001000, $this->treasuryService->getTreasuryBalance($this->treasury->id)); // 1,000,000 + 1000

        // Verify 3 payments were recorded
        $this->assertEquals(3, $invoice->payments()->count());
    }

    /** @test */
    public function test_payment_with_discount()
    {
        // Scenario: Invoice 1000, pay 900 with 100 discount
        // Expected: Balance should reflect correct calculation, Treasury = 1,000,900

        // Add stock before sales
        StockMovement::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $invoice = SalesInvoice::create([
            'invoice_number' => 'SI-005',
            'warehouse_id' => $this->warehouse->id,
            'partner_id' => $this->customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'discount_type' => 'fixed',
            'discount_value' => 0,
            'subtotal' => 1000,
            'discount' => 0,
            'total' => 1000,
            'paid_amount' => 0,
            'remaining_amount' => 1000,
        ]);

        $invoice->items()->create([
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        // Post the invoice
        $this->stockService->postSalesInvoice($invoice);
        $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
        $invoice->update(['status' => 'posted']);

        // Recalculate partner balance after status update
        $this->customer->recalculateBalance();

        // Pay with discount
        $payment = $this->treasuryService->recordInvoicePayment(
            $invoice,
            900,
            100, // discount
            $this->treasury->id,
            'Payment with settlement discount'
        );

        // Assertions
        $this->assertEquals(100, $payment->discount);
        $this->assertEquals(1000900, $this->treasuryService->getTreasuryBalance($this->treasury->id)); // 1,000,000 + 900

        // Balance should be 0 (invoice total 1000 - payment 900 - discount 100)
        // FIXED: The discount is now properly reducing the partner balance
        $this->customer->refresh();
        $this->assertEquals(0, round($this->customer->current_balance, 2));
    }
}
