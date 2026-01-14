<?php

use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
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

test('it deducts stock correctly when sales invoice is posted', function () {
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
        'reference_id' => 'test-purchase-id',
    ]);

    $invoice = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_type' => 'small',
                'unit_price' => '100.00',
                'subtotal' => '1000.00',
                'discount' => '0.00',
                'total' => '1000.00',
            ],
        ]
    );

    $this->stockService->postSalesInvoice($invoice);

    $movement = StockMovement::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe(-10); // Negative for sale
    expect($movement->type)->toBe('sale');
    expect((float)$movement->cost_at_time)->toBe(50.0);
});

test('it creates correct stock movement with base unit quantity', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add initial stock (120 pieces = 10 cartons)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 120,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase-id',
    ]);

    $invoice = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 2, // 2 cartons
                'unit_type' => 'large',
                'unit_price' => '1200.00',
                'subtotal' => '2400.00',
                'discount' => '0.00',
                'total' => '2400.00',
            ],
        ]
    );

    $this->stockService->postSalesInvoice($invoice);

    $movement = StockMovement::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // Should store 24 pieces (2 cartons * 12 factor)
    expect($movement->quantity)->toBe(-24);
});

test('it validates stock availability before posting', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add only 10 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 10,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase-id',
    ]);

    $invoice = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 20, // More than available
                'unit_type' => 'small',
                'unit_price' => '100.00',
                'subtotal' => '2000.00',
                'discount' => '0.00',
                'total' => '2000.00',
            ],
        ]
    );

    expect(fn () => $this->stockService->postSalesInvoice($invoice))
        ->toThrow(Exception::class, 'المخزون غير كافٍ');
});

test('it throws exception when invoice not draft', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $invoice = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 10,
                'unit_type' => 'small',
                'unit_price' => '100.00',
                'subtotal' => '1000.00',
                'discount' => '0.00',
                'total' => '1000.00',
            ],
        ]
    );

    // Post the invoice first
    $invoice->update(['status' => 'posted']);

    // Try to post again
    expect(fn () => $this->stockService->postSalesInvoice($invoice))
        ->toThrow(Exception::class, 'الفاتورة ليست في حالة مسودة');
});

test('it handles concurrent sales of same product', function () {
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
        'reference_id' => 'test-purchase-id',
    ]);

    $invoice1 = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 50,
                'unit_type' => 'small',
                'unit_price' => '100.00',
                'subtotal' => '5000.00',
                'discount' => '0.00',
                'total' => '5000.00',
            ],
        ]
    );

    $invoice2 = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 50,
                'unit_type' => 'small',
                'unit_price' => '100.00',
                'subtotal' => '5000.00',
                'discount' => '0.00',
                'total' => '5000.00',
            ],
        ]
    );

    // Post both invoices - should succeed
    $this->stockService->postSalesInvoice($invoice1);
    $this->stockService->postSalesInvoice($invoice2);

    $finalStock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($finalStock)->toBe(0); // 100 - 50 - 50 = 0
});

test('it handles zero quantity sale', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $invoice = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        items: [
            [
                'product_id' => $product->id,
                'quantity' => 0,
                'unit_type' => 'small',
                'unit_price' => '100.00',
                'subtotal' => '0.00',
                'discount' => '0.00',
                'total' => '0.00',
            ],
        ]
    );

    // Should not throw exception for zero quantity
    $this->stockService->postSalesInvoice($invoice);

    $movement = StockMovement::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    expect($movement->quantity)->toBe(0);
});
