<?php

use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Services\FinancialReportService;
use App\Services\ReportService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->financialReportService = app(FinancialReportService::class);
    $this->reportService = app(ReportService::class);
    $this->warehouse = \App\Models\Warehouse::factory()->create();
    $this->treasury = TestHelpers::createFundedTreasury('100000.0000');
    $this->units = TestHelpers::createUnits();
});

test('it generates complete report with real data', function () {
    // Create transactions
    $customer = Partner::factory()->customer()->create([
        'current_balance' => '5000.0000',
    ]);

    $supplier = Partner::factory()->supplier()->create([
        'current_balance' => '3000.0000',
    ]);

    SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'total' => '10000.0000',
        'created_at' => now(),
    ]);

    PurchaseInvoice::factory()->create([
        'partner_id' => $supplier->id,
        'status' => 'posted',
        'total' => '5000.0000',
        'created_at' => now(),
    ]);

    FixedAsset::create([
        'name' => 'Office Equipment',
        'purchase_amount' => '20000.0000',
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
    ]);

    Expense::create([
        'title' => 'Office Rent',
        'amount' => '2000.0000',
        'treasury_id' => $this->treasury->id,
        'expense_date' => now(),
    ]);

    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');

    $report = $this->financialReportService->generateReport($fromDate, $toDate);

    // Verify all calculations match actual data
    expect((float) $report['total_sales'])->toBe(10000.0);
    expect((float) $report['total_purchases'])->toBe(5000.0);
    expect((float) $report['fixed_assets_value'])->toBe(20000.0);
    expect((float) $report['expenses'])->toBe(2000.0);
    expect((float) $report['total_debtors'])->toBe(8000.0); // 5000 (Customer) + 3000 (Supplier Advance)
    expect((float) $report['total_creditors'])->toBe(0.0);
});

test('it generates partner statement with all transactions', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '1000.0000',
    ]);

    // Create transactions
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
        'created_at' => now(),
    ]);

    InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '2000.0000',
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    expect($statement['transactions'])->toHaveCount(2);
    expect((float) $statement['opening_balance'])->toBe(1000.0);
    expect((float) $statement['closing_balance'])->toBe(4000.0); // 1000 + 5000 - 2000
});

test('it generates stock card with all movements', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Create movements
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
        'created_at' => now()->subDays(5),
    ]);

    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity' => -30,
        'cost_at_time' => '50.00',
        'reference_type' => 'sales_invoice',
        'reference_id' => 'sale-1',
        'created_at' => now(),
    ]);

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $stockCard = $this->reportService->getStockCard(
        $product->id,
        $this->warehouse->id,
        $startDate,
        $endDate
    );

    expect($stockCard['movements'])->toHaveCount(2);
    expect($stockCard['total_in'])->toBe(100);
    expect($stockCard['total_out'])->toBe(30);
    expect($stockCard['closing_stock'])->toBe(70);
});
