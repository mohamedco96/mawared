<?php

namespace Tests\Feature\Services;

use App\Models\Expense;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '1000.00',
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesInvoice($salesInvoice, $treasury->id);

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

        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1500.00',
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesInvoice($salesInvoice, $treasury->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'collection')->get());

        $transaction = TreasuryTransaction::where('type', 'collection')->first();
        $this->assertEquals('1500.0000', $transaction->amount); // Positive
        $this->assertEquals($customer->id, $transaction->partner_id);

        // Verify partner balance increased (customer debt)
        $customer->refresh();
        $this->assertEquals('1500.0000', $customer->current_balance);

        // Verify partner balance from service method
        $partnerBalance = $service->getPartnerBalance($customer->id);
        $this->assertEquals('1500', $partnerBalance);
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
        $supplier = Partner::factory()->supplier()->create();

        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '800.00',
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'payment')->get());

        $transaction = TreasuryTransaction::where('type', 'payment')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('-800.0000', $transaction->amount); // Negative
        $this->assertEquals($supplier->id, $transaction->partner_id);
        $this->assertEquals('purchase_invoice', $transaction->reference_type);
        $this->assertEquals($purchaseInvoice->id, $transaction->reference_id);

        // Verify treasury balance decreased
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('-800', $treasuryBalance);

        // Verify partner balance unchanged (cash payment)
        $supplier->refresh();
        $this->assertEquals('0.0000', $supplier->current_balance);
    }

    public function test_creates_payment_transaction_and_updates_partner_balance_when_credit_purchase_invoice_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $supplier = Partner::factory()->supplier()->create();

        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1200.00',
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'payment')->get());

        $transaction = TreasuryTransaction::where('type', 'payment')->first();
        $this->assertEquals('-1200.0000', $transaction->amount); // Negative
        $this->assertEquals($supplier->id, $transaction->partner_id);

        // Verify partner balance updated (we owe supplier)
        $supplier->refresh();
        $this->assertEquals('-1200.0000', $supplier->current_balance);
    }

    // ===== SALES RETURN OPERATIONS =====

    public function test_creates_negative_refund_transaction_when_cash_sales_return_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();

        $salesReturn = SalesReturn::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '200.00',
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesReturn($salesReturn, $treasury->id);

        // ASSERT
        $this->assertCount(1, TreasuryTransaction::where('type', 'refund')->get());

        $transaction = TreasuryTransaction::where('type', 'refund')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('-200.0000', $transaction->amount); // NEGATIVE (money leaves treasury)
        $this->assertEquals($customer->id, $transaction->partner_id);
        $this->assertEquals('sales_return', $transaction->reference_type);
        $this->assertEquals($salesReturn->id, $transaction->reference_id);

        // Verify treasury balance decreased
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('-200', $treasuryBalance);

        // Verify partner balance unchanged (cash payment)
        $customer->refresh();
        $this->assertEquals('0.0000', $customer->current_balance);
    }

    public function test_creates_negative_refund_transaction_and_updates_partner_balance_when_credit_sales_return_is_posted(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();

        // First create a credit sales invoice to establish customer debt
        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
        ]);

        $service = new TreasuryService();
        $service->postSalesInvoice($salesInvoice, $treasury->id);

        // Verify customer debt
        $customer->refresh();
        $this->assertEquals('1000.0000', $customer->current_balance);

        // Create return
        $salesReturn = SalesReturn::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '300.00',
        ]);

        // ACT
        $service->postSalesReturn($salesReturn, $treasury->id);

        // ASSERT
        $refundTransaction = TreasuryTransaction::where('type', 'refund')->first();
        $this->assertEquals('-300.0000', $refundTransaction->amount); // Negative (reduces customer debt)

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

        $purchaseReturn = PurchaseReturn::factory()->create([
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '150.00',
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postPurchaseReturn($purchaseReturn, $treasury->id);

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

        // First create a credit purchase invoice to establish supplier credit
        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '2000.00',
        ]);

        $service = new TreasuryService();
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

        // Verify supplier credit (we owe them)
        $supplier->refresh();
        $this->assertEquals('-2000.0000', $supplier->current_balance);

        // Create return
        $purchaseReturn = PurchaseReturn::factory()->create([
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '400.00',
        ]);

        // ACT
        $service->postPurchaseReturn($purchaseReturn, $treasury->id);

        // ASSERT
        $refundTransaction = TreasuryTransaction::where('type', 'refund')->first();
        $this->assertEquals('400.0000', $refundTransaction->amount); // Positive (reduces what we owe supplier)

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

        // Create customer with existing debt
        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
        ]);

        $service = new TreasuryService();
        $service->postSalesInvoice($salesInvoice, $treasury->id);

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

        $this->assertEquals('-600.0000', $collectionTransaction->amount); // Negative (reduces partner balance)

        // Verify partner balance decreased (customer paid their debt)
        $customer->refresh();
        $this->assertEquals('400.0000', $customer->current_balance); // 1000 - 600
    }

    public function test_records_payment_to_supplier_decreases_treasury_and_decreases_partner_balance(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $supplier = Partner::factory()->supplier()->create();

        // Create supplier with existing credit (we owe them)
        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1500.00',
        ]);

        $service = new TreasuryService();
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

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

        $this->assertEquals('800.0000', $paymentTransaction->amount); // Positive (reduces what we owe)

        // Verify partner balance updated (we owe less)
        $supplier->refresh();
        $this->assertEquals('-700.0000', $supplier->current_balance); // -1500 + 800
    }

    public function test_applies_discount_to_collection_transaction(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();

        // Create customer with debt of 1000
        $salesInvoice = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
        ]);

        $service = new TreasuryService();
        $service->postSalesInvoice($salesInvoice, $treasury->id);

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

        // Final amount should be -(1000 - 50) = -950 (negative for partner balance reduction)
        $this->assertEquals('-950.0000', $collectionTransaction->amount);

        // Verify partner balance correctly updated
        $customer->refresh();
        $this->assertEquals('50.0000', $customer->current_balance); // 1000 - 950
    }

    public function test_applies_discount_to_payment_transaction(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $supplier = Partner::factory()->supplier()->create();

        // Create supplier with credit
        $purchaseInvoice = PurchaseInvoice::factory()->create([
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '2000.00',
        ]);

        $service = new TreasuryService();
        $service->postPurchaseInvoice($purchaseInvoice, $treasury->id);

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

        // Final amount should be (2000 - 100) = 1900 (positive to reduce supplier credit)
        $this->assertEquals('1900.0000', $paymentTransaction->amount);

        // Verify partner balance correctly updated
        $supplier->refresh();
        $this->assertEquals('-100.0000', $supplier->current_balance); // -2000 + 1900
    }

    // ===== EXPENSE & REVENUE TESTS =====

    public function test_creates_negative_expense_transaction(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
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

        // Verify treasury balance decreased
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('-500', $treasuryBalance);
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
        $this->assertCount(1, TreasuryTransaction::where('type', 'income')->get());

        $transaction = TreasuryTransaction::where('type', 'income')->first();
        $this->assertEquals($treasury->id, $transaction->treasury_id);
        $this->assertEquals('750.0000', $transaction->amount); // Positive
        $this->assertEquals('revenue', $transaction->reference_type);
        $this->assertEquals($revenue->id, $transaction->reference_id);

        // Verify treasury balance increased
        $treasuryBalance = $service->getTreasuryBalance($treasury->id);
        $this->assertEquals('750', $treasuryBalance);
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
        ]);

        // Manually set wrong balance
        $customer->update(['current_balance' => '999.00']);

        // ACT
        $service->updatePartnerBalance($customer->id);

        // ASSERT
        $customer->refresh();
        $this->assertEquals('1500.0000', $customer->current_balance);
    }

    // ===== EDGE CASES & TRANSACTION SAFETY =====

    public function test_creates_default_treasury_when_none_exists(): void
    {
        // ARRANGE
        // Ensure no treasury exists
        Treasury::query()->delete();

        $salesInvoice = SalesInvoice::factory()->create([
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '100.00',
        ]);

        $service = new TreasuryService();

        // ACT
        $service->postSalesInvoice($salesInvoice); // No treasury ID provided

        // ASSERT
        $this->assertEquals(1, Treasury::count());

        $defaultTreasury = Treasury::first();
        $this->assertEquals('الخزينة الرئيسية', $defaultTreasury->name);
        $this->assertEquals('cash', $defaultTreasury->type);

        // Verify transaction was created with default treasury
        $transaction = TreasuryTransaction::first();
        $this->assertEquals($defaultTreasury->id, $transaction->treasury_id);
    }

    public function test_handles_multiple_transactions_for_same_partner_correctly(): void
    {
        // ARRANGE
        $treasury = Treasury::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $service = new TreasuryService();

        // Create multiple invoices/returns for same partner
        $invoice1 = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1000.00',
        ]);

        $invoice2 = SalesInvoice::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '500.00',
        ]);

        $return = SalesReturn::factory()->create([
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '200.00',
        ]);

        // ACT
        $service->postSalesInvoice($invoice1, $treasury->id);
        $service->postSalesInvoice($invoice2, $treasury->id);
        $service->postSalesReturn($return, $treasury->id);

        // ASSERT
        // Partner balance is cumulative sum
        $customer->refresh();
        $this->assertEquals('1300.0000', $customer->current_balance); // 1000 + 500 - 200

        // Verify balance calculation method
        $balance = $service->getPartnerBalance($customer->id);
        $this->assertEquals('1300', $balance);
    }
}
