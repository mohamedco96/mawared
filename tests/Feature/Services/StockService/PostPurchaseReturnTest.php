<?php

use App\Models\Product;
use App\Models\PurchaseReturn;
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

test('it removes stock correctly when purchase return is posted', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Initial stock: 100 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 30,
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '1500.00',
    ]);

    $this->stockService->postPurchaseReturn($return);

    $return->refresh();

    $movement = StockMovement::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(-30); // NEGATIVE for return (removes stock)
    expect($movement->type)->toBe('purchase_return');
});

test('it validates stock availability before purchase return', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Initial stock: only 20 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 20,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 30, // More than available
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '1500.00',
    ]);

    expect(fn () => $this->stockService->postPurchaseReturn($return))
        ->toThrow(Exception::class, 'المخزون غير كافٍ');
});

test('it converts large unit cost to base unit for purchase return', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Initial stock: 100 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    // Return 2 cartons @ 600 EGP/carton
    // Expected: cost_at_time should be 600 / 12 = 50 EGP per piece
    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 2, // 2 cartons
        'unit_type' => 'large',
        'unit_cost' => '600.00', // 600 EGP per carton
        'discount' => '0.00',
        'total' => '1200.00',
    ]);

    $this->stockService->postPurchaseReturn($return);

    $movement = StockMovement::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->first();

    // CRITICAL: cost_at_time should be 50.00 (600 / 12), NOT 600.00
    expect((float)$movement->cost_at_time)->toBe(50.00);
    expect($movement->quantity)->toBe(-24); // 2 cartons * 12 = 24 pieces (negative)
});

test('it updates average cost after purchase return', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
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

    $product->update(['avg_cost' => '53.3333']); // (100*50 + 50*60) / 150

    // Return 20 units @ 60 EGP (from purchase 2)
    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 20,
        'unit_type' => 'small',
        'unit_cost' => '60.00',
        'discount' => '0.00',
        'total' => '1200.00',
    ]);

    $this->stockService->postPurchaseReturn($return);

    $product->refresh();
    // Expected: (100*50 + 30*60) / 130 = 6800 / 130 = 52.3077
    expect(abs((float)$product->avg_cost - 52.3077))->toBeLessThan(0.0001);
});

test('it throws exception when return not draft', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'posted',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '500.00',
    ]);

    expect(fn () => $this->stockService->postPurchaseReturn($return))
        ->toThrow(Exception::class, 'المرتجع ليس في حالة مسودة');
});
