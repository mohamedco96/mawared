<?php

use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\ReportService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->reportService = app(ReportService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->units = TestHelpers::createUnits();
});

test('it generates stock card with correct opening stock', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock before start date (should be in opening stock)
    $movement = StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);
    // Manually update created_at since it's not fillable
    $movement->created_at = now()->subMonths(2);
    $movement->saveQuietly();

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $stockCard = $this->reportService->getStockCard(
        $product->id,
        $this->warehouse->id,
        $startDate,
        $endDate
    );

    expect($stockCard)->toBeArray();
    expect($stockCard)->toHaveKey('opening_stock');
    expect($stockCard['opening_stock'])->toBe(100);
});

test('it includes all movements in date range', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Purchase in date range
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
        'created_at' => now(),
    ]);

    // Sale in date range
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

    $stockCard = $this->reportService->getStockCard(
        $product->id,
        $this->warehouse->id,
        $startDate,
        $endDate
    );

    expect($stockCard['movements'])->toHaveCount(2);
});

test('it calculates running stock correctly', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Opening stock: 100
    $movement = StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'opening',
    ]);
    $movement->created_at = now()->subMonths(2);
    $movement->saveQuietly();

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Purchase: +50
    $purchase = StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
    ]);
    $purchase->created_at = now()->subDays(5);
    $purchase->saveQuietly();

    // Sale: -30
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

    $stockCard = $this->reportService->getStockCard(
        $product->id,
        $this->warehouse->id,
        $startDate,
        $endDate
    );

    $movements = $stockCard['movements'];

    // First movement (purchase): balance = 100 + 50 = 150
    expect($movements[0]['balance'])->toBe(150);

    // Second movement (sale): balance = 150 - 30 = 120
    expect($movements[1]['balance'])->toBe(120);

    // Closing stock should be 120
    expect($stockCard['closing_stock'])->toBe(120);
});

test('it handles warehouse filtering', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $warehouse2 = Warehouse::factory()->create();

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Movement in warehouse 1
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
        'created_at' => now(),
    ]);

    // Movement in warehouse 2 (should be excluded)
    StockMovement::create([
        'warehouse_id' => $warehouse2->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-2',
        'created_at' => now(),
    ]);

    $stockCard = $this->reportService->getStockCard(
        $product->id,
        $this->warehouse->id,
        $startDate,
        $endDate
    );

    // Should only include movements from warehouse 1
    expect($stockCard['movements'])->toHaveCount(1);
    expect($stockCard['total_in'])->toBe(100);
});

test('it handles product with no movements', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $stockCard = $this->reportService->getStockCard(
        $product->id,
        $this->warehouse->id,
        $startDate,
        $endDate
    );

    expect($stockCard['movements'])->toHaveCount(0);
    expect($stockCard['opening_stock'])->toBe(0);
    expect($stockCard['closing_stock'])->toBe(0);
});

test('it calculates total in and out correctly', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Purchases: 100 + 50 = 150
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
        'created_at' => now(),
    ]);

    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-2',
        'created_at' => now(),
    ]);

    // Sales: 30 + 20 = 50
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

    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity' => -20,
        'cost_at_time' => '50.00',
        'reference_type' => 'sales_invoice',
        'reference_id' => 'sale-2',
        'created_at' => now(),
    ]);

    $stockCard = $this->reportService->getStockCard(
        $product->id,
        $this->warehouse->id,
        $startDate,
        $endDate
    );

    expect($stockCard['total_in'])->toBe(150);
    expect($stockCard['total_out'])->toBe(50);
});
