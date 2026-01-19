<?php

use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Warehouse;
use App\Services\FinancialReportService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->reportService = app(FinancialReportService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
    $this->units = TestHelpers::createUnits();
});

test('it calculates fixed assets value correctly', function () {
    FixedAsset::create([
        'name' => 'Asset 1',
        'purchase_amount' => '30000.0000',
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
    ]);

    FixedAsset::create([
        'name' => 'Asset 2',
        'purchase_amount' => '20000.0000',
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    expect((float)$report['fixed_assets_value'])->toBe(50000.0);
});

test('it calculates inventory value at date', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Purchase: 100 units @ 50 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
        'created_at' => now()->subDays(10),
    ]);

    $product->update(['avg_cost' => '50.00']);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Inventory value: 100 * 50 = 5000
    expect((float)$report['ending_inventory'])->toBe(5000.0);
});

test('it calculates total debtors correctly', function () {
    // Customer with positive balance (owes us - normal receivable)
    Partner::factory()->customer()->create([
        'current_balance' => '5000.0000',
    ]);

    Partner::factory()->customer()->create([
        'current_balance' => '3000.0000',
    ]);

    // Supplier with NEGATIVE balance (advance payment we made - reclassified as debtor)
    Partner::factory()->supplier()->create([
        'current_balance' => '-2000.0000',
    ]);

    // Supplier with POSITIVE balance (we owe them - should NOT be in debtors)
    Partner::factory()->supplier()->create([
        'current_balance' => '1500.0000',
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Debtors = Customers(+) + abs(Suppliers(-))
    // = (5000 + 3000) + abs(-2000) = 8000 + 2000 = 10000
    expect((float)$report['total_debtors'])->toBe(10000.0);
});

test('it calculates total creditors correctly', function () {
    // Supplier with POSITIVE balance (we owe them - normal payable)
    Partner::factory()->supplier()->create([
        'current_balance' => '5000.0000',
    ]);

    Partner::factory()->supplier()->create([
        'current_balance' => '3000.0000',
    ]);

    // Customer with NEGATIVE balance (advance payment received - reclassified as creditor)
    Partner::factory()->customer()->create([
        'current_balance' => '-2000.0000',
    ]);

    // Customer with POSITIVE balance (they owe us - should NOT be in creditors)
    Partner::factory()->customer()->create([
        'current_balance' => '1500.0000',
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Creditors = Suppliers(+) + abs(Customers(-))
    // = (5000 + 3000) + abs(-2000) = 8000 + 2000 = 10000
    expect((float)$report['total_creditors'])->toBe(10000.0);
});

test('it calculates total cash from all treasuries', function () {
    // Clear existing treasury transactions from beforeEach
    TreasuryTransaction::truncate();
    
    $treasury1 = TestHelpers::createFundedTreasury('10000.0000');
    $treasury2 = TestHelpers::createFundedTreasury('5000.0000');

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Total cash: 10000 (treasury1) + 5000 (treasury2) = 15000
    expect((float)$report['total_cash'])->toBe(15000.0);
});

test('it calculates total sales in date range', function () {
    $customer = Partner::factory()->customer()->create();
    
    SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'total' => '10000.0000',
        'created_at' => now(),
    ]);

    SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'total' => '5000.0000',
        'created_at' => now(),
    ]);

    // Draft invoice (should not be counted)
    SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'draft',
        'total' => '3000.0000',
        'created_at' => now(),
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Should only count posted invoices
    expect((float)$report['total_sales'])->toBe(15000.0);
});

test('it calculates settlement discounts correctly', function () {
    $customer = Partner::factory()->customer()->create();
    $supplier = Partner::factory()->supplier()->create();

    $salesInvoice = SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'total' => '10000.0000',
    ]);

    $purchaseInvoice = PurchaseInvoice::factory()->create([
        'partner_id' => $supplier->id,
        'status' => 'posted',
        'total' => '5000.0000',
    ]);

    // Create invoice payments with discounts
    InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $salesInvoice->id,
        'amount' => '9500.0000',
        'discount' => '500.0000', // Discount allowed
        'payment_date' => now(),
        'partner_id' => $customer->id,
    ]);

    InvoicePayment::create([
        'payable_type' => 'purchase_invoice',
        'payable_id' => $purchaseInvoice->id,
        'amount' => '4800.0000',
        'discount' => '200.0000', // Discount received
        'payment_date' => now(),
        'partner_id' => $supplier->id,
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    expect((float)$report['discount_allowed'])->toBe(500.0);
    expect((float)$report['discount_received'])->toBe(200.0);
});

test('it excludes shareholders from debtors and creditors', function () {
    // Customer with positive balance (debtor)
    Partner::factory()->customer()->create([
        'current_balance' => '5000.0000',
    ]);

    // Supplier with positive balance (creditor - we owe them)
    Partner::factory()->supplier()->create([
        'current_balance' => '3000.0000',
    ]);

    // Shareholder with positive balance (should NOT be included in either)
    Partner::factory()->create([
        'type' => 'shareholder',
        'current_balance' => '10000.0000',
    ]);

    // Shareholder with negative balance (should NOT be included in either)
    Partner::factory()->create([
        'type' => 'shareholder',
        'current_balance' => '-5000.0000',
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Shareholders should not be included in either calculation
    expect((float)$report['total_debtors'])->toBe(5000.0);
    expect((float)$report['total_creditors'])->toBe(3000.0);
});

test('it handles zero values correctly', function () {
    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    expect((float)$report['fixed_assets_value'])->toBe(0.0);
    expect((float)$report['total_sales'])->toBe(0.0);
    expect((float)$report['total_purchases'])->toBe(0.0);
    expect((float)$report['expenses'])->toBe(0.0);
    expect((float)$report['revenues'])->toBe(0.0);
});
