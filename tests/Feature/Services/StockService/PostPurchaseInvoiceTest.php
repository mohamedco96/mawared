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

test('it adds stock correctly when purchase invoice is posted', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 100,
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '5000.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $movement = StockMovement::where('reference_type', 'purchase_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(100); // Positive for purchase
    expect($movement->type)->toBe('purchase');
    expect($movement->cost_at_time)->toBe('50.0000');
});

test('it updates weighted average cost after purchase', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Initial purchase: 100 units @ 50 EGP
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'initial-purchase',
    ]);

    $product->update(['avg_cost' => '50.00']);

    // Second purchase: 50 units @ 60 EGP
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 50,
        'unit_type' => 'small',
        'unit_cost' => '60.00',
        'discount' => '0.00',
        'total' => '3000.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $product->refresh();
    // Expected: (100 * 50 + 50 * 60) / 150 = 8000 / 150 = 53.3333
    expect(abs((float)$product->avg_cost - 53.3333))->toBeLessThan(0.0001);
});

test('it converts large unit cost to base unit - critical bug fix', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    // Purchase 5 cartons @ 600 EGP/carton
    // Expected: cost_at_time should be 600 / 12 = 50 EGP per piece
    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 5, // 5 cartons
        'unit_type' => 'large',
        'unit_cost' => '600.00', // 600 EGP per carton
        'discount' => '0.00',
        'total' => '3000.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $movement = StockMovement::where('reference_type', 'purchase_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // CRITICAL: cost_at_time should be 50.00 (600 / 12), NOT 600.00
    expect((float)$movement->cost_at_time)->toBe(50.00);
    expect($movement->quantity)->toBe(60); // 5 cartons * 12 = 60 pieces

    $product->refresh();
    // avg_cost should be 50.00 (since this is the first purchase)
    expect((float)$product->avg_cost)->toBe(50.00);
});

test('it updates product prices when new selling price provided', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00',
        retailPrice: '100.00'
    );

    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 100,
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '5000.00',
        'new_selling_price' => '120.00',
        'new_large_selling_price' => '1440.00', // 120 * 12
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $product->refresh();
    expect((float)$product->retail_price)->toBe(120.00);
    expect((float)$product->large_retail_price)->toBe(1440.00);
});

test('it throws exception when invoice not draft', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'posted',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 100,
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '5000.00',
    ]);

    expect(fn () => $this->stockService->postPurchaseInvoice($invoice))
        ->toThrow(Exception::class, 'الفاتورة ليست في حالة مسودة');
});

test('it handles multiple purchases affecting avg cost', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Purchase 1: 100 units @ 50 EGP
    $invoice1 = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice1->items()->create([
        'product_id' => $product->id,
        'quantity' => 100,
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '5000.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice1);
    $product->refresh();
    expect((float)$product->avg_cost)->toBe(50.00);

    // Purchase 2: 50 units @ 60 EGP
    $invoice2 = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice2->items()->create([
        'product_id' => $product->id,
        'quantity' => 50,
        'unit_type' => 'small',
        'unit_cost' => '60.00',
        'discount' => '0.00',
        'total' => '3000.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice2);
    $product->refresh();
    expect(abs((float)$product->avg_cost - 53.3333))->toBeLessThan(0.0001);

    // Purchase 3: 20 units @ 70 EGP
    $invoice3 = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice3->items()->create([
        'product_id' => $product->id,
        'quantity' => 20,
        'unit_type' => 'small',
        'unit_cost' => '70.00',
        'discount' => '0.00',
        'total' => '1400.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice3);
    $product->refresh();
    // Expected: (100*50 + 50*60 + 20*70) / 170 = 9400 / 170 = 55.2941
    expect(abs((float)$product->avg_cost - 55.2941))->toBeLessThan(0.0001);
});

test('it handles very small cost values with precision', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '0.0001'
    );

    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 1000,
        'unit_type' => 'small',
        'unit_cost' => '0.0001',
        'discount' => '0.00',
        'total' => '0.10',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $movement = StockMovement::where('reference_type', 'purchase_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // Should preserve DECIMAL(15,4) precision
    expect($movement->cost_at_time)->toBe('0.0001');
});
