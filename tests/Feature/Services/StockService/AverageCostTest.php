<?php

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->units = TestHelpers::createUnits();
});

test('it calculates weighted average correctly with single purchase', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '0.00'
    );

    // Single purchase: 100 units @ 50 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $this->stockService->updateProductAvgCost($product->id);

    $product->refresh();
    expect((float)$product->avg_cost)->toBe(50.00);
});

test('it calculates weighted average correctly with multiple purchases', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '0.00'
    );

    // Purchase 1: 100 units @ 50 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
    ]);

    // Purchase 2: 50 units @ 60 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
        'cost_at_time' => '60.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-2',
    ]);

    $this->stockService->updateProductAvgCost($product->id);

    $product->refresh();
    // Expected: (100 * 50 + 50 * 60) / 150 = 8000 / 150 = 53.3333
    expect(abs((float)$product->avg_cost - 53.3333))->toBeLessThan(0.0001);
});

test('it handles large unit purchases with cost conversion - critical bug fix', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '0.00'
    );

    // Purchase 1: 100 pieces @ 50 EGP/piece
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
    ]);

    // Purchase 2: 5 cartons @ 600 EGP/carton
    // This should be stored as 60 pieces @ 50 EGP/piece (600 / 12)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 60, // 5 cartons * 12
        'cost_at_time' => '50.00', // 600 / 12 = 50 (base unit cost)
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-2',
    ]);

    $this->stockService->updateProductAvgCost($product->id);

    $product->refresh();
    // Expected: (100 * 50 + 60 * 50) / 160 = 8000 / 160 = 50.00
    expect((float)$product->avg_cost)->toBe(50.00);
});

test('it handles zero quantity purchases', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Create purchase with zero quantity (should be filtered out)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 0,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'zero-purchase',
    ]);

    $originalAvgCost = $product->avg_cost;

    $this->stockService->updateProductAvgCost($product->id);

    $product->refresh();
    // Should remain unchanged since zero quantity purchases are filtered
    expect($product->avg_cost)->toBe($originalAvgCost);
});

test('it handles purchases with different costs correctly', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '0.00'
    );

    // Purchase 1: 10 units @ 10 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 10,
        'cost_at_time' => '10.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
    ]);

    // Purchase 2: 20 units @ 20 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 20,
        'cost_at_time' => '20.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-2',
    ]);

    // Purchase 3: 30 units @ 30 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 30,
        'cost_at_time' => '30.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-3',
    ]);

    $this->stockService->updateProductAvgCost($product->id);

    $product->refresh();
    // Expected: (10*10 + 20*20 + 30*30) / 60 = 1400 / 60 = 23.3333
    expect(abs((float)$product->avg_cost - 23.3333))->toBeLessThan(0.0001);
});

test('it excludes non-purchase movements from average cost calculation', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '0.00'
    );

    // Purchase: 100 units @ 50 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'purchase-1',
    ]);

    // Sale: 30 units (should NOT affect avg_cost)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity' => -30,
        'cost_at_time' => '50.00',
        'reference_type' => 'sales_invoice',
        'reference_id' => 'sale-1',
    ]);

    $this->stockService->updateProductAvgCost($product->id);

    $product->refresh();
    // Should only consider purchase movements, so avg_cost = 50.00
    expect((float)$product->avg_cost)->toBe(50.00);
});
