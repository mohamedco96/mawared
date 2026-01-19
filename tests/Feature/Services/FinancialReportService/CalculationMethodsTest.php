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
    // Customer with positive balance (owes us)
    $customer1 = Partner::factory()->customer()->create([
        'current_balance' => '5000.0000',
    ]);

    $customer2 = Partner::factory()->customer()->create([
        'current_balance' => '3000.0000',
    ]);

    // Supplier with positive balance (should be included as debtor)
    $supplier = Partner::factory()->supplier()->create([
        'current_balance' => '2000.0000',
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Should count all partners with positive balances regardless of type (except shareholders)
    expect((float)$report['total_debtors'])->toBe(10000.0); // 5000 + 3000 + 2000
});

test('it calculates total creditors correctly', function () {
    // Supplier with negative balance (we owe them)
    $supplier1 = Partner::factory()->supplier()->create([
        'current_balance' => '-5000.0000',
    ]);

    $supplier2 = Partner::factory()->supplier()->create([
        'current_balance' => '-3000.0000',
    ]);

    // Customer with negative balance (should be included as creditor)
    $customer = Partner::factory()->customer()->create([
        'current_balance' => '-2000.0000',
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Should count all partners with negative balances (absolute value)
    expect((float)$report['total_creditors'])->toBe(10000.0); // abs(-5000) + abs(-3000) + abs(-2000)
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
    $customer = Partner::factory()->customer()->create([
        'current_balance' => '5000.0000',
    ]);

    $supplier = Partner::factory()->supplier()->create([
        'current_balance' => '-3000.0000',
    ]);

    $shareholder = Partner::factory()->create([
        'type' => 'shareholder',
        'current_balance' => '10000.0000',
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');
    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Shareholder should not be included
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
