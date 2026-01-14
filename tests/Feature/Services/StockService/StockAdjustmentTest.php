<?php

use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->units = TestHelpers::createUnits();
    $this->user = User::factory()->create();
});

test('it adds stock when addition adjustment is posted', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $adjustment = StockAdjustment::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'status' => 'draft',
        'type' => 'opening',
        'quantity' => 50,
        'notes' => 'Found stock',
        'created_by' => $this->user->id,
    ]);

    $this->stockService->postStockAdjustment($adjustment);

    $adjustment->refresh();

    $movement = StockMovement::where('reference_type', 'stock_adjustment')
        ->where('reference_id', $adjustment->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(50); // Positive for addition
    expect($movement->type)->toBe('adjustment_in');
});

test('it removes stock when subtraction adjustment is posted', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $adjustment = StockAdjustment::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'status' => 'draft',
        'type' => 'damage',
        'quantity' => 30,
        'notes' => 'Stock loss',
        'created_by' => $this->user->id,
    ]);

    $this->stockService->postStockAdjustment($adjustment);

    $movement = StockMovement::where('reference_type', 'stock_adjustment')
        ->where('reference_id', $adjustment->id)
        ->first();

    expect($movement->quantity)->toBe(-30); // Negative for subtraction
    expect($movement->type)->toBe('adjustment_out');
});

test('it handles damage adjustment type', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $adjustment = StockAdjustment::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'status' => 'draft',
        'type' => 'damage',
        'quantity' => 10,
        'notes' => 'Damaged items',
        'created_by' => $this->user->id,
    ]);

    $this->stockService->postStockAdjustment($adjustment);

    $movement = StockMovement::where('reference_type', 'stock_adjustment')
        ->where('reference_id', $adjustment->id)
        ->first();

    expect($movement->quantity)->toBe(-10); // Negative for damage
    expect($movement->type)->toBe('adjustment_out');
});

test('it handles gift adjustment type', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $adjustment = StockAdjustment::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'status' => 'draft',
        'type' => 'gift',
        'quantity' => 5,
        'notes' => 'Gift items',
        'created_by' => $this->user->id,
    ]);

    $this->stockService->postStockAdjustment($adjustment);

    $movement = StockMovement::where('reference_type', 'stock_adjustment')
        ->where('reference_id', $adjustment->id)
        ->first();

    expect($movement->quantity)->toBe(-5); // Negative for gift
    expect($movement->type)->toBe('adjustment_out');
});

test('it validates stock availability for subtraction adjustments', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add only 20 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 20,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $adjustment = StockAdjustment::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'status' => 'draft',
        'type' => 'damage',
        'quantity' => 30, // More than available
        'notes' => 'Stock loss',
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->stockService->postStockAdjustment($adjustment))
        ->toThrow(Exception::class, 'المخزون غير كافٍ');
});

test('it throws exception when adjustment not draft', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $adjustment = StockAdjustment::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'status' => 'posted',
        'type' => 'opening',
        'quantity' => 50,
        'notes' => 'Found stock',
        'created_by' => $this->user->id,
    ]);

    expect(fn () => $this->stockService->postStockAdjustment($adjustment))
        ->toThrow(Exception::class, 'التسوية ليست في حالة مسودة');
});

test('it handles zero quantity adjustment', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $adjustment = StockAdjustment::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'status' => 'draft',
        'type' => 'opening',
        'quantity' => 0,
        'notes' => 'Zero adjustment',
        'created_by' => $this->user->id,
    ]);

    // Should not throw exception
    $this->stockService->postStockAdjustment($adjustment);

    $movement = StockMovement::where('reference_type', 'stock_adjustment')
        ->where('reference_id', $adjustment->id)
        ->first();

    expect($movement->quantity)->toBe(0);
});
