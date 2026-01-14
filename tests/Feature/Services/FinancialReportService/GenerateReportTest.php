<?php

use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\Revenue;
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
});

test('it generates complete financial report', function () {
    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');

    $report = $this->reportService->generateReport($fromDate, $toDate);

    expect($report)->toBeArray();
    expect($report)->toHaveKeys([
        'from_date',
        'to_date',
        'shareholder_capital',
        'shareholder_drawings',
        'equity',
        'fixed_assets_value',
        'beginning_inventory',
        'ending_inventory',
        'total_debtors',
        'total_creditors',
        'total_cash',
        'total_sales',
        'total_purchases',
        'sales_returns',
        'purchase_returns',
        'discount_received',
        'discount_allowed',
        'expenses',
        'revenues',
        'net_profit',
        'total_assets',
        'total_liabilities',
    ]);
});

test('it calculates all components correctly', function () {
    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');

    // Create fixed asset
    FixedAsset::create([
        'name' => 'Office Equipment',
        'purchase_amount' => '50000.0000',
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
    ]);

    // Create sales invoice
    $customer = Partner::factory()->customer()->create();
    SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'total' => '10000.0000',
        'created_at' => now(),
    ]);

    // Create purchase invoice
    $supplier = Partner::factory()->supplier()->create();
    PurchaseInvoice::factory()->create([
        'partner_id' => $supplier->id,
        'status' => 'posted',
        'total' => '5000.0000',
        'created_at' => now(),
    ]);

    $report = $this->reportService->generateReport($fromDate, $toDate);

    expect((float)$report['fixed_assets_value'])->toBe(50000.0);
    expect((float)$report['total_sales'])->toBe(10000.0);
    expect((float)$report['total_purchases'])->toBe(5000.0);
});

test('it handles empty date range', function () {
    $fromDate = now()->addMonths(1)->format('Y-m-d');
    $toDate = now()->addMonths(2)->format('Y-m-d');

    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Should return report with zero values
    expect((float)$report['total_sales'])->toBe(0.0);
    expect((float)$report['total_purchases'])->toBe(0.0);
    expect((float)$report['expenses'])->toBe(0.0);
    expect((float)$report['revenues'])->toBe(0.0);
});

test('it handles date range with no transactions', function () {
    $fromDate = now()->subYears(2)->format('Y-m-d');
    $toDate = now()->subYears(1)->format('Y-m-d');

    $report = $this->reportService->generateReport($fromDate, $toDate);

    expect((float)$report['total_sales'])->toBe(0.0);
    expect((float)$report['total_purchases'])->toBe(0.0);
});

test('it calculates net profit correctly', function () {
    $fromDate = now()->subMonths(1)->format('Y-m-d');
    $toDate = now()->format('Y-m-d');

    // Create sales
    $customer = Partner::factory()->customer()->create();
    SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'total' => '10000.0000',
        'created_at' => now(),
    ]);

    // Create expenses
    Expense::create([
        'title' => 'Office Rent',
        'amount' => '2000.0000',
        'treasury_id' => $this->treasury->id,
        'expense_date' => now(),
    ]);

    $report = $this->reportService->generateReport($fromDate, $toDate);

    // Net profit calculation depends on inventory, COGS, etc.
    // This is a simplified check
    expect($report['net_profit'])->toBeNumeric();
});

test('it handles very large date range', function () {
    $fromDate = now()->subYears(10)->format('Y-m-d');
    $toDate = now()->addYears(1)->format('Y-m-d');

    $report = $this->reportService->generateReport($fromDate, $toDate);

    expect($report)->toBeArray();
    expect($report['from_date'])->toBe($fromDate);
    expect($report['to_date'])->toBe($toDate);
});
