<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
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
    $this->supplier = Partner::factory()->supplier()->create();
    $this->product = Product::factory()->create([
        'avg_cost' => 50,
    ]);

    // Create and post a purchase invoice with 10 units
    $this->invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $this->invoice->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 10,
        'unit_price' => 50,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 500,
    ]);

    // Post the invoice
    $this->stockService->postPurchaseInvoice($this->invoice);
    $this->treasuryService->postPurchaseInvoice($this->invoice);
    $this->invoice->update(['status' => 'posted']);
});

test('can return partial quantity from purchase invoice', function () {
    // Create a return for 5 units
    $return = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 250,
        'total' => 250,
    ]);

    $return->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 250,
    ]);

    // Post the return
    $this->stockService->postPurchaseReturn($return);
    $this->treasuryService->postPurchaseReturn($return);
    $return->update(['status' => 'posted']);

    // Verify available quantity
    $availableQty = $this->invoice->fresh()->getAvailableReturnQuantity(
        $this->product->id,
        'small'
    );
    expect($availableQty)->toBe(5);
});

test('can return remaining quantity after partial purchase return', function () {
    // First return: 5 units
    $return1 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 250,
        'total' => 250,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 250,
    ]);

    $this->stockService->postPurchaseReturn($return1);
    $this->treasuryService->postPurchaseReturn($return1);
    $return1->update(['status' => 'posted']);

    // Second return: remaining 5 units
    $return2 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 250,
        'total' => 250,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 250,
    ]);

    $this->stockService->postPurchaseReturn($return2);
    $this->treasuryService->postPurchaseReturn($return2);
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

test('prevents return when purchase invoice is fully returned', function () {
    // First return: full 10 units
    $return1 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 10,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 500,
    ]);

    $this->stockService->postPurchaseReturn($return1);
    $this->treasuryService->postPurchaseReturn($return1);
    $return1->update(['status' => 'posted']);

    // Try to create another return
    $return2 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 50,
        'total' => 50,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 1,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 50,
    ]);

    // Should throw exception
    expect(fn() => $this->stockService->postPurchaseReturn($return2))
        ->toThrow(\Exception::class, 'لا يمكن تأكيد المرتجع: قيمة المرتجع تتجاوز قيمة الفاتورة المتبقية');
});

test('prevents purchase return quantity exceeding available quantity', function () {
    // First return: 5 units
    $return1 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 250,
        'total' => 250,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 250,
    ]);

    $this->stockService->postPurchaseReturn($return1);
    $this->treasuryService->postPurchaseReturn($return1);
    $return1->update(['status' => 'posted']);

    // Try to return 10 units (only 5 available)
    $return2 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 500,
        'total' => 500,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 10,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 500,
    ]);

    // Should throw exception
    expect(fn() => $this->stockService->postPurchaseReturn($return2))
        ->toThrow(\Exception::class);
});

test('purchase invoice isFullyReturned returns false for partial returns', function () {
    // Return only 5 units
    $return = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 250,
        'total' => 250,
    ]);

    $return->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 250,
    ]);

    $this->stockService->postPurchaseReturn($return);
    $this->treasuryService->postPurchaseReturn($return);
    $return->update(['status' => 'posted']);

    expect($this->invoice->fresh()->isFullyReturned())->toBeFalse();
});

test('getReturnedQuantity returns correct total across multiple purchase returns', function () {
    // First return: 3 units
    $return1 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 150,
        'total' => 150,
    ]);

    $return1->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 3,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 150,
    ]);

    $this->stockService->postPurchaseReturn($return1);
    $return1->update(['status' => 'posted']);

    // Second return: 2 units
    $return2 = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 100,
        'total' => 100,
    ]);

    $return2->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 2,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 100,
    ]);

    $this->stockService->postPurchaseReturn($return2);
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

test('does not count draft purchase returns in returned quantity', function () {
    // Create a draft return (not posted)
    $draftReturn = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $this->invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $this->supplier->id,
        'status' => 'draft',
        'subtotal' => 250,
        'total' => 250,
    ]);

    $draftReturn->items()->create([
        'product_id' => $this->product->id,
        'unit_type' => 'small',
        'quantity' => 5,
        'unit_cost' => 50,
        'discount' => 0,
        'total' => 250,
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
