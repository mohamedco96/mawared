<?php

use App\Models\Product;
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

test('it returns correct stock level for product and warehouse', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $stock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($stock)->toBe(100);
});

test('it uses lock for update when requested', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    // Should not throw when lock is requested
    $stock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id, lock: true);
    expect($stock)->toBe(100);
});

test('it returns validation message with stock info', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock: 60 pieces = 5 cartons
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 60,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    // Test with small unit
    $result = $this->stockService->getStockValidationMessage(
        $this->warehouse->id,
        $product->id,
        requiredQuantity: 50,
        unitType: 'small'
    );

    expect($result['is_available'])->toBeTrue();
    expect($result['current_stock'])->toBe(60);
    expect($result['display_stock'])->toBe(60);

    // Test with large unit
    $result = $this->stockService->getStockValidationMessage(
        $this->warehouse->id,
        $product->id,
        requiredQuantity: 60, // 5 cartons in base units
        unitType: 'large'
    );

    expect($result['is_available'])->toBeTrue();
    expect($result['current_stock'])->toBe(60);
    expect($result['display_stock'])->toBe(5); // 60 / 12 = 5 cartons
});

test('it returns unavailable message when stock insufficient', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock: 50 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $result = $this->stockService->getStockValidationMessage(
        $this->warehouse->id,
        $product->id,
        requiredQuantity: 100,
        unitType: 'small'
    );

    expect($result['is_available'])->toBeFalse();
    expect($result['current_stock'])->toBe(50);
    expect($result['message'])->toContain('المخزون المتاح');
});

test('it handles soft deleted movements', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock
    $movement = StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $stock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($stock)->toBe(100);

    // Soft delete the movement
    $movement->delete();

    // Stock should be 0 (soft-deleted movements are excluded)
    $stock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($stock)->toBe(0);
});

test('it validates stock availability correctly', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock: 100 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    expect($this->stockService->validateStockAvailability($this->warehouse->id, $product->id, 50))->toBeTrue();
    expect($this->stockService->validateStockAvailability($this->warehouse->id, $product->id, 100))->toBeTrue();
    expect($this->stockService->validateStockAvailability($this->warehouse->id, $product->id, 101))->toBeFalse();
});
