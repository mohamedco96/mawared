<?php

use App\Models\Product;
use App\Models\SalesReturn;
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

test('it restores stock correctly when sales return is posted', function () {
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

    // Sale: -50 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'sale',
        'quantity' => -50,
        'cost_at_time' => '50.00',
        'reference_type' => 'sales_invoice',
        'reference_id' => 'test-sale',
    ]);

    $return = SalesReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 30,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'discount' => '0.00',
        'total' => '3000.00',
    ]);

    $this->stockService->postSalesReturn($return);

    $return->refresh();

    $movement = StockMovement::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(30); // POSITIVE for return (restores stock)
    expect($movement->type)->toBe('sale_return');
});

test('it handles large unit returns correctly', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $return = SalesReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    // Return 2 cartons = 24 pieces
    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_type' => 'large',
        'unit_price' => '1200.00',
        'discount' => '0.00',
        'total' => '2400.00',
    ]);

    $this->stockService->postSalesReturn($return);

    $movement = StockMovement::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();

    // Should store 24 pieces (2 cartons * 12)
    expect($movement->quantity)->toBe(24);
});

test('it throws exception when return not draft', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $return = SalesReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'posted',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'discount' => '0.00',
        'total' => '1000.00',
    ]);

    expect(fn () => $this->stockService->postSalesReturn($return))
        ->toThrow(Exception::class, 'المرتجع ليس في حالة مسودة');
});
