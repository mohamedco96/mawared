<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->treasuryService = app(TreasuryService::class);

    // Create test data
    $this->warehouse = Warehouse::factory()->create();
    $this->partner = Partner::factory()->customer()->create();
    $this->product = Product::factory()->create([
        'retail_price' => 100,
        'avg_cost' => 60,
    ]);

    // First, add stock to the warehouse via a purchase invoice
    $supplier = Partner::factory()->supplier()->create();
    $purchaseInvoice = \App\Models\PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $supplier->id,
        'status' => 'draft',
        'subtotal' => 600,
        'total' => 600,
    ]);

    $purchaseInvoice->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 20, // Purchase 20 units so we have enough stock
        'unit_price' => 30,
        'unit_cost' => 30,
        'discount' => 0,
        'total' => 600,
    ]);

    // Post the purchase invoice to add stock
    $this->stockService->postPurchaseInvoice($purchaseInvoice);
    $this->treasuryService->postPurchaseInvoice($purchaseInvoice);
    $purchaseInvoice->update(['status' => 'posted']);

    // Create and post a sales invoice with 10 units
    $this->invoice = SalesInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $this->invoice->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 10,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 1000,
    ]);

    // Post the invoice
    $this->stockService->postSalesInvoice($this->invoice);
    $this->treasuryService->postSalesInvoice($this->invoice);
    $this->invoice->update(['status' => 'posted']);
});

test('can return partial quantity from invoice', function () {
    // Create a return for 5 units
    $return = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $return->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 500,
    ]);

    // Post the return
    $this->stockService->postSalesReturn($return);
    $this->treasuryService->postSalesReturn($return);
    $return->update(['status' => 'posted']);

    // Verify available quantity
    $availableQty = $this->invoice->fresh()->getAvailableReturnQuantity(
        $this->product->id,
        'small'
    );
    expect($availableQty)->toBe(5);
});

test('can return remaining quantity after partial return', function () {
    // First return: 5 units
    $return1 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 500,
    ]);

    $this->stockService->postSalesReturn($return1);
    $this->treasuryService->postSalesReturn($return1);
    $return1->update(['status' => 'posted']);

    // Second return: remaining 5 units
    $return2 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 500,
    ]);

    $this->stockService->postSalesReturn($return2);
    $this->treasuryService->postSalesReturn($return2);
    $return2->update(['status' => 'posted']);

    // Verify invoice is fully returned
    expect($this->invoice->fresh()->isFullyReturned())->toBeTrue();

    // Verify no quantity available for return
    $availableQty = $this->invoice->fresh()->getAvailableReturnQuantity(
        $this->product->id,
        'small'
    );
    expect($availableQty)->toBe(0);
});

test('prevents return when invoice is fully returned', function () {
    // First return: full 10 units
    $return1 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 10,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 1000,
    ]);

    $this->stockService->postSalesReturn($return1);
    $this->treasuryService->postSalesReturn($return1);
    $return1->update(['status' => 'posted']);

    // Try to create another return
    $return2 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 100,
        'total' => 100,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 100,
    ]);

    // Should throw exception
    expect(fn() => $this->stockService->postSalesReturn($return2))
        ->toThrow(\Exception::class, 'لا يمكن تأكيد المرتجع: قيمة المرتجع تتجاوز قيمة الفاتورة المتبقية');
});

test('prevents return quantity exceeding available quantity', function () {
    // First return: 5 units
    $return1 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 500,
    ]);

    $this->stockService->postSalesReturn($return1);
    $this->treasuryService->postSalesReturn($return1);
    $return1->update(['status' => 'posted']);

    // Try to return 10 units (only 5 available)
    $return2 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 1000,
        'total' => 1000,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 10,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 1000,
    ]);

    // Should throw exception
    expect(fn() => $this->stockService->postSalesReturn($return2))
        ->toThrow(\Exception::class);
});

test('isFullyReturned returns false for partial returns', function () {
    // Return only 5 units
    $return = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $return->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 500,
    ]);

    $this->stockService->postSalesReturn($return);
    $this->treasuryService->postSalesReturn($return);
    $return->update(['status' => 'posted']);

    expect($this->invoice->fresh()->isFullyReturned())->toBeFalse();
});

test('getReturnedQuantity returns correct total across multiple returns', function () {
    // First return: 3 units
    $return1 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 300,
        'total' => 300,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 3,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 300,
    ]);

    $this->stockService->postSalesReturn($return1);
    $return1->update(['status' => 'posted']);

    // Second return: 2 units
    $return2 = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 200,
        'total' => 200,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 2,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 200,
    ]);

    $this->stockService->postSalesReturn($return2);
    $return2->update(['status' => 'posted']);

    // Should return total of 5
    $returnedQty = $this->invoice->fresh()->getReturnedQuantity(
        $this->product->id,
        'small'
    );
    expect($returnedQty)->toBe(5);

    // Available should be 5
    $availableQty = $this->invoice->fresh()->getAvailableReturnQuantity(
        $this->product->id,
        'small'
    );
    expect($availableQty)->toBe(5);
});

test('does not count draft returns in returned quantity', function () {
    // Create a draft return (not posted)
    $draftReturn = SalesReturn::factory()->create([
        'sales_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->partner->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $draftReturn->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_price' => 100,
        'discount' => 0,
        'total' => 500,
    ]);

    // Draft returns should not be counted
    $returnedQty = $this->invoice->fresh()->getReturnedQuantity(
        $this->product->id,
        'small'
    );
    expect($returnedQty)->toBe(0);

    // All quantity should still be available
    $availableQty = $this->invoice->fresh()->getAvailableReturnQuantity(
        $this->product->id,
        'small'
    );
    expect($availableQty)->toBe(10);
});
