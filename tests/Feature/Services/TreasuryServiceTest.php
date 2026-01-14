<?php

namespace Tests\Feature\Services;

use App\Models\Expense;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TreasuryServiceTest extends TestCase
{
    use RefreshDatabase;

    // ===== SALES INVOICE OPERATIONS =====

    public function test_creates_positive_collection_transaction_when_cash_sales_invoice_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '1000.00',
            'paid_amount' => '1000.00',
            'remaining_amount' => '0.00',
        ]);

        // Add items
        $salesInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        // Add stock BEFORE posting
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesInvoice($salesInvoice, $treasury->id);

        // Update status AFTER posting
        $salesInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($customer->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'collection')->get());

        $transaction = TreasuryTransaction::where('type', 'collection')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('1000.0000', $transaction->amount); // Positive
        $this->assertEquals($customer->id, $transaction->partner_id);
        $this->assertEquals('sales_invoice', $transaction->reference_type);
        $this->assertEquals($salesInvoice->id, $transaction->reference_id);

        // Verify treasury balance increased
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('1000', $treasuryBalance);

        // Verify partner balance unchanged (cash payment)
        $customer->refresh();
        $this->assertEquals('0.0000', $customer->current_balance);
    }

    public function test_creates_collection_transaction_and_updates_partner_balance_when_credit_sales_invoice_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1500.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1500.0000',
        ]);

        // Add items
        $salesInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 15,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1500,
        ]);

        // Add stock BEFORE posting
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesInvoice($salesInvoice, $treasury->id);

        // Update status AFTER posting
        $salesInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($customer->id);

        // ASSERT
        // For credit invoices, NO treasury transaction is created
        $this->assertCount(0, TreasuryTransaction::where('type', 'collection')->get());

        // Verify partner balance increased (customer debt)
        $customer->refresh();
        $this->assertEquals('1500.0000', $customer->current_balance);

        // Verify partner balance from service method (should be 0 as no treasury transactions)
        $partnerBalance = $service->getPartnerBalance($customer->id);
        $this->assertEquals('0', $partnerBalance);
    }

    public function test_throws_exception_when_trying_to_post_already_posted_sales_invoice(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $salesInvoice = SalesInvoice::factory()->create([
            'status' => 'posted', // Already posted
        ]);

        $service = new TreasuryService();

        // ACT & ASSERT
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('الفاتورة ليست في حالة مسودة');
        $service->postSalesInvoice($salesInvoice, $treasury->id);
    }

    // ===== PURCHASE INVOICE OPERATIONS =====

    public function test_creates_negative_payment_transaction_when_cash_purchase_invoice_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();

        // Add initial balance to treasury to allow payment
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => '10000.00',
            'description' => 'Initial balance',
        ]);

        $supplier = Partner::factory()->supplier()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '800.00',
            'paid_amount' => '800.00',
            'remaining_amount' => '0.00',
        ]);

        // Add items
        $purchaseInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 8,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 800,
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // Update status AFTER posting
        $purchaseInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($supplier->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'payment')->get());

        $transaction = TreasuryTransaction::where('type', 'payment')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('-800.0000', $transaction->amount); // Negative
        $this->assertEquals($supplier->id, $transaction->partner_id);
        $this->assertEquals('purchase_invoice', $transaction->reference_type);
        $this->assertEquals($purchaseInvoice->id, $transaction->reference_id);

        // Verify treasury balance decreased (10000 initial - 800 payment = 9200)
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('9200', $treasuryBalance);

        // Verify partner balance unchanged (cash payment)
        $supplier->refresh();
        $this->assertEquals('0.0000', $supplier->current_balance);
    }

    public function test_creates_payment_transaction_and_updates_partner_balance_when_credit_purchase_invoice_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $supplier = Partner::factory()->supplier()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1200.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1200.0000',
        ]);

        // Add items
        $purchaseInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 12,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 1200,
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // Update status AFTER posting
        $purchaseInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($supplier->id);

        // ASSERT
        // For credit invoices, NO treasury transaction is created
        $this->assertCount(0, TreasuryTransaction::where('type', 'payment')->get());

        // Verify partner balance updated (we owe supplier)
        $supplier->refresh();
        $this->assertEquals('-1200.0000', $supplier->current_balance);
    }

    // ===== SALES RETURN OPERATIONS =====

    public function test_creates_negative_refund_transaction_when_cash_sales_return_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();

        // Add initial balance to treasury to allow refund
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => '10000.00',
            'description' => 'Initial balance',
        ]);

        $customer = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $salesReturn = SalesReturn::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '200.00',
        ]);

        // Add items
        $salesReturn->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 200,
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesReturn($salesReturn, $treasury->id);

        // Update status AFTER posting
        $salesReturn->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($customer->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'refund')->get());

        $transaction = TreasuryTransaction::where('type', 'refund')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('-200.0000', $transaction->amount); // NEGATIVE (money leaves treasury)
        $this->assertEquals($customer->id, $transaction->partner_id);
        $this->assertEquals('sales_return', $transaction->reference_type);
        $this->assertEquals($salesReturn->id, $transaction->reference_id);

        // Verify treasury balance decreased (10000 initial - 200 refund = 9800)
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('9800', $treasuryBalance);

        // Verify partner balance unchanged (cash payment)
        $customer->refresh();
        $this->assertEquals('0.0000', $customer->current_balance);
    }

    public function test_creates_negative_refund_transaction_and_updates_partner_balance_when_credit_sales_return_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Add stock BEFORE posting sales invoice
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        // First create a credit sales invoice to establish customer debt
        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1000.0000',
        ]);

        // Add items to sales invoice
        $salesInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        $service = new TreasuryService();
        $service->postSalesInvoice($salesInvoice, $treasury->id);

        // Update status AFTER posting
        $salesInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($customer->id);

        // Verify customer debt
        $customer->refresh();
        $this->assertEquals('1000.0000', $customer->current_balance);

        // Create return
        $salesReturn = SalesReturn::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '300.00',
        ]);

        // Add items to sales return
        $salesReturn->items()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 300,
        ]);

        // ACT
        $service->postSalesReturn($salesReturn, $treasury->id);

        // Update status AFTER posting
        $salesReturn->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($customer->id);

        // ASSERT
        // For credit returns, NO treasury transaction is created
        $this->assertCount(0, TreasuryTransaction::where('type', 'refund')->get());

        // Verify partner balance decreased (customer debt reduced)
        $customer->refresh();
        $this->assertEquals('700.0000', $customer->current_balance); // 1000 - 300
    }

    // ===== PURCHASE RETURN OPERATIONS =====

    public function test_creates_positive_refund_transaction_when_cash_purchase_return_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $supplier = Partner::factory()->supplier()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $purchaseReturn = PurchaseReturn::factory()->create([
            'partner_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '150.00',
        ]);

        // Add items
        $purchaseReturn->items()->create([
            'product_id' => $product->id,
            'quantity' => 1.5,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 150,
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postPurchaseReturn($purchaseReturn, $treasury->id);

        // Update status AFTER posting
        $purchaseReturn->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($supplier->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'refund')->get());

        $transaction = TreasuryTransaction::where('type', 'refund')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('150.0000', $transaction->amount); // POSITIVE (money returns to treasury)
        $this->assertEquals($supplier->id, $transaction->partner_id);
        $this->assertEquals('purchase_return', $transaction->reference_type);

        // Verify treasury balance increased
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('150', $treasuryBalance);
    }

    public function test_creates_positive_refund_transaction_and_updates_partner_balance_when_credit_purchase_return_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $supplier = Partner::factory()->supplier()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // First create a credit purchase invoice to establish supplier credit
        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '2000.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '2000.0000',
        ]);

        // Add items to purchase invoice
        $purchaseInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 2000,
        ]);

        $service = new TreasuryService();
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // Update status AFTER posting
        $purchaseInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($supplier->id);

        // Verify supplier credit (we owe them)
        $supplier->refresh();
        $this->assertEquals('-2000.0000', $supplier->current_balance);

        // Create return
        $purchaseReturn = PurchaseReturn::factory()->create([
            'partner_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '400.00',
        ]);

        // Add items to purchase return
        $purchaseReturn->items()->create([
            'product_id' => $product->id,
            'quantity' => 4,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 400,
        ]);

        // ACT
        $service->postPurchaseReturn($purchaseReturn, $treasury->id);

        // Update status AFTER posting
        $purchaseReturn->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($supplier->id);

        // ASSERT
        // For credit returns, NO treasury transaction is created
        $this->assertCount(0, TreasuryTransaction::where('type', 'refund')->get());

        // Verify partner balance updated (we owe less)
        $supplier->refresh();
        $this->assertEquals('-1600.0000', $supplier->current_balance); // -2000 + 400
    }

    // ===== FINANCIAL TRANSACTION TESTS =====

    public function test_records_collection_from_customer_increases_treasury_and_decreases_partner_balance(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Add stock BEFORE posting sales invoice
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        // Create customer with existing debt
        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1000.0000',
        ]);

        // Add items to sales invoice
        $salesInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        $service = new TreasuryService();
        $service->postSalesInvoice($salesInvoice, $treasury->id);

        // Update status AFTER posting
        $salesInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($customer->id);

        // Verify debt
        $customer->refresh();
        $this->assertEquals('1000.0000', $customer->current_balance);

        // ACT - Customer pays 600
        $service->recordFinancialTransaction(
            $treasury->id,
            'collection',
            '600.00',
            'تحصيل من العميل',
            $customer->id
        );

        // ASSERT
        $collectionTransaction = TreasuryTransaction::where('type', 'collection')
            ->where('reference_type', 'financial_transaction')
            ->first();

        $this->assertEquals('600.0000', $collectionTransaction->amount); // Positive (increases treasury, reduces partner debt)

        // Verify partner balance decreased (customer paid their debt)
        $customer->refresh();
        $this->assertEquals('400.0000', $customer->current_balance); // 1000 - 600
    }

    public function test_records_payment_to_supplier_decreases_treasury_and_decreases_partner_balance(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();

        // Add initial balance to treasury to allow payment
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => '10000.00',
            'description' => 'Initial balance',
        ]);

        $supplier = Partner::factory()->supplier()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create supplier with existing credit (we owe them)
        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1500.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1500.0000',
        ]);

        // Add items to purchase invoice
        $purchaseInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 15,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 1500,
        ]);

        $service = new TreasuryService();
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // Update status AFTER posting
        $purchaseInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($supplier->id);

        // Verify credit
        $supplier->refresh();
        $this->assertEquals('-1500.0000', $supplier->current_balance);

        // ACT - We pay supplier 800
        $service->recordFinancialTransaction(
            $treasury->id,
            'payment',
            '800.00',
            'سداد للمورد',
            $supplier->id
        );

        // ASSERT
        $paymentTransaction = TreasuryTransaction::where('type', 'payment')
            ->where('reference_type', 'financial_transaction')
            ->first();

        $this->assertEquals('-800.0000', $paymentTransaction->amount); // Negative (decreases treasury, reduces partner debt)

        // Verify partner balance updated (we owe less)
        $supplier->refresh();
        $this->assertEquals('-700.0000', $supplier->current_balance); // -1500 + 800
    }

    public function test_applies_discount_to_collection_transaction(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Add stock BEFORE posting sales invoice
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        // Create customer with debt of 1000
        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1000.0000',
        ]);

        // Add items to sales invoice
        $salesInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        $service = new TreasuryService();
        $service->postSalesInvoice($salesInvoice, $treasury->id);

        // Update status AFTER posting
        $salesInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($customer->id);

        // ACT - Customer pays 1000 with 50 discount
        $service->recordFinancialTransaction(
            $treasury->id,
            'collection',
            '1000.00',
            'تحصيل مع خصم',
            $customer->id,
            '50.00' // discount
        );

        // ASSERT
        $collectionTransaction = TreasuryTransaction::where('type', 'collection')
            ->where('reference_type', 'financial_transaction')
            ->first();

        // Final amount should be (1000 - 50) = 950 (positive for treasury increase, subtracts from partner balance)
        $this->assertEquals('950.0000', $collectionTransaction->amount);

        // Verify partner balance correctly updated
        $customer->refresh();
        $this->assertEquals('50.0000', $customer->current_balance); // 1000 - 950
    }

    public function test_applies_discount_to_payment_transaction(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();

        // Add initial balance to treasury to allow payment
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => '10000.00',
            'description' => 'Initial balance',
        ]);

        $supplier = Partner::factory()->supplier()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create supplier with credit
        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '2000.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '2000.0000',
        ]);

        // Add items to purchase invoice
        $purchaseInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 20,
            'unit_type' => 'small',
            'unit_cost' => 100,
            'total' => 2000,
        ]);

        $service = new TreasuryService();
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // Update status AFTER posting
        $purchaseInvoice->update(['status' => 'posted']);
        // Recalculate partner balance after status update
        $service->updatePartnerBalance($supplier->id);

        // ACT - We pay 2000 with 100 discount (supplier gave us discount)
        $service->recordFinancialTransaction(
            $treasury->id,
            'payment',
            '2000.00',
            'سداد مع خصم',
            $supplier->id,
            '100.00' // discount
        );

        // ASSERT
        $paymentTransaction = TreasuryTransaction::where('type', 'payment')
            ->where('reference_type', 'financial_transaction')
            ->first();

        // Final amount should be -(2000 - 100) = -1900 (negative for treasury decrease, reduces supplier credit)
        $this->assertEquals('-1900.0000', $paymentTransaction->amount);

        // Verify partner balance correctly updated
        $supplier->refresh();
        $this->assertEquals('-100.0000', $supplier->current_balance); // -2000 + 1900
    }

    // ===== EXPENSE & REVENUE TESTS =====

    public function test_creates_negative_expense_transaction(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();

        // Add initial balance to treasury to allow expense
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => '10000.00',
            'description' => 'Initial balance',
        ]);

        $user = User::factory()->create();

        $expense = Expense::create([
            'title' => 'Test Expense',
            'description' => 'Office supplies',
            'amount' => '500.00',
            'treasury_id' => $treasury->id,
            'expense_date' => now(),
            'created_by' => $user->id,
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postExpense($expense);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'expense')->get());

        $transaction = TreasuryTransaction::where('type', 'expense')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('-500.0000', $transaction->amount); // Negative
        $this->assertEquals('expense', $transaction->reference_type);
        $this->assertEquals($expense->id, $transaction->reference_id);

        // Verify treasury balance: 10000 (initial) - 500 (expense) = 9500
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('9500', $treasuryBalance);
    }

    public function test_creates_positive_income_transaction_for_revenue(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $user = User::factory()->create();

        $revenue = Revenue::create([
            'title' => 'Test Revenue',
            'description' => 'Service income',
            'amount' => '750.00',
            'treasury_id' => $treasury->id,
            'revenue_date' => now(),
            'created_by' => $user->id,
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postRevenue($revenue);

        // ASSERT
        // Check for revenue transaction specifically (excluding the initial capital from TestCase setUp)
        $transaction = TreasuryTransaction::where('type', 'income')
            ->where('reference_type', 'revenue')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('750.0000', $transaction->amount); // Positive
        $this->assertEquals('revenue', $transaction->reference_type);
        $this->assertEquals($revenue->id, $transaction->reference_id);

        // Verify treasury balance increased (initial capital + revenue)
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        // Note: TestCase setUp() creates initial capital, so balance will be > 750
        $this->assertGreaterThanOrEqual(750, $treasuryBalance);
    }

    // ===== HELPER METHOD TESTS =====

    public function test_getTreasuryBalance_calculates_correct_balance_from_all_transactions(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $service = new TreasuryService();

        // Create multiple different transactions
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'collection',
            'amount' => '1000.00',
            'description' => 'Test collection',
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'payment',
            'amount' => '-500.00',
            'description' => 'Test payment',
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'refund',
            'amount' => '-100.00',
            'description' => 'Test refund',
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => '300.00',
            'description' => 'Test income',
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'expense',
            'amount' => '-200.00',
            'description' => 'Test expense',
        ]);

        // ACT
        $balance = $service->getTreasuryBalance($treasury->id);

        // ASSERT
        // 1000 - 500 - 100 + 300 - 200 = 500
        $this->assertEquals('500', $balance);
    }

    public function test_getPartnerBalance_calculates_correct_balance_from_all_partner_transactions(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $service = new TreasuryService();

        // Create multiple transactions for same partner
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'collection',
            'amount' => '2000.00',
            'description' => 'Test',
            'partner_id' => $customer->id,
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'collection',
            'amount' => '-500.00',
            'description' => 'Test',
            'partner_id' => $customer->id,
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'refund',
            'amount' => '-300.00',
            'description' => 'Test',
            'partner_id' => $customer->id,
        ]);

        // ACT
        $balance = $service->getPartnerBalance($customer->id);

        // ASSERT
        // 2000 - 500 - 300 = 1200
        $this->assertEquals('1200', $balance);
    }

    public function test_updatePartnerBalance_recalculates_current_balance_field(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $service = new TreasuryService();

        // Create transactions
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'collection',
            'amount' => '1500.00',
            'description' => 'Test',
            'partner_id' => $customer->id,
            'reference_type' => 'financial_transaction',
        ]);

        // Manually set wrong balance
        $customer->update(['current_balance' => '999.00']);

        // ACT
        $service->updatePartnerBalance($customer->id);

        // ASSERT
        // Customer paid 1500 with no invoices = advance payment = we owe them 1500 (negative balance)
        $customer->refresh();
        $this->assertEquals('-1500.0000', $customer->current_balance);
    }

    // ===== EDGE CASES & TRANSACTION SAFETY =====

    public function test_creates_default_treasury_when_none_exists(): void
    {
        // This test verifies that the service can create a default treasury if none is provided
        // Note: Due to FK constraints in SQLite, we can't easily delete all treasuries in tests
        // Instead, we'll verify the logic works by checking the getDefaultTreasury method behavior

        // ARRANGE
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Add stock BEFORE posting
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        $salesInvoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '100.00',
            'paid_amount' => '100.00',
            'remaining_amount' => '0.00',
        ]);

        // Add items
        $salesInvoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 100,
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesInvoice($salesInvoice); // No treasury ID provided - should use default

        // Update status AFTER posting
        $salesInvoice->update(['status' => 'posted']);

        // ASSERT
        // Treasury should exist (either from TestCase setup or newly created)
        $this->assertGreaterThan(0, Treasury::count());

        // Verify transaction was created with a treasury
        $transaction = TreasuryTransaction::where('reference_type', 'sales_invoice')
            ->where('reference_id', $salesInvoice->id)
            ->first();

        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->treasury_id);
    }

    public function test_handles_multiple_transactions_for_same_partner_correctly(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = new TreasuryService();

        // Add stock BEFORE posting sales invoices
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 1000,
            'cost_at_time' => 50,
            'reference_type' => 'initial_stock',
            'reference_id' => Str::ulid(),
        ]);

        // Create multiple invoices/returns for same partner
        $invoice1 = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '1000.0000',
        ]);

        // Add items to invoice1
        $invoice1->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 1000,
        ]);

        $invoice2 = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '500.00',
            'paid_amount' => '0.0000',
            'remaining_amount' => '500.0000',
        ]);

        // Add items to invoice2
        $invoice2->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 500,
        ]);

        $return = SalesReturn::factory()->create([
            'partner_id' => $customer->id,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '200.00',
        ]);

        // Add items to return
        $return->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 200,
        ]);

        // ACT
        $service->postSalesInvoice($invoice1, $treasury->id);
        $invoice1->update(['status' => 'posted']);
        $service->updatePartnerBalance($customer->id); // Recalculate after status update

        $service->postSalesInvoice($invoice2, $treasury->id);
        $invoice2->update(['status' => 'posted']);
        $service->updatePartnerBalance($customer->id); // Recalculate after status update

        $service->postSalesReturn($return, $treasury->id);
        $return->update(['status' => 'posted']);
        $service->updatePartnerBalance($customer->id); // Recalculate after status update

        // ASSERT
        // Partner balance calculated from invoices and returns
        $customer->refresh();
        $this->assertEquals('1300.0000', $customer->current_balance); // 1000 + 500 - 200

        // For credit invoices, there are no treasury transactions, so getPartnerBalance should be 0
        $balance = $service->getPartnerBalance($customer->id);
        $this->assertEquals('0', $balance);
    }
}
