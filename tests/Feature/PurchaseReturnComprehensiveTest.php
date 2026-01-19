<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TreasuryService;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->treasuryService = app(TreasuryService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->treasury = TestHelpers::createFundedTreasury('100000.0000');
    $this->units = TestHelpers::createUnits();
});

/**
 * HELPER: Simulate the full posting process as done in Filament EditPurchaseReturn::afterSave
 */
function fullPostPurchaseReturn($return, $treasuryId) {
    DB::transaction(function () use ($return, $treasuryId) {
        $return->load('items.product');
        app(StockService::class)->postPurchaseReturn($return);
        app(TreasuryService::class)->postPurchaseReturn($return, $treasuryId);
        
        $return->status = 'posted';
        $return->saveQuietly();
        
        // CRITICAL: Recalculate partner balance AFTER the status is set to 'posted'
        if ($return->partner_id) {
            app(TreasuryService::class)->updatePartnerBalance($return->partner_id);
        }
    });
}

test('it handles cash purchase return correctly in a full transaction', function () {
    $partner = Partner::factory()->supplier()->create();
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');
    
    // Initial Stock (Add 50)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
        'cost_at_time' => '100.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $return = PurchaseReturn::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'subtotal' => '1000.00',
        'total' => '1000.00',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostPurchaseReturn($return, $this->treasury->id);

    // 1. Stock Check
    $movement = StockMovement::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->first();
    expect($movement->quantity)->toBe(-10);
    expect((float)$movement->cost_at_time)->toBe(100.00);

    // 2. Treasury Check
    $transaction = TreasuryTransaction::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->first();
    expect((float)$transaction->amount)->toBe(1000.00); // Money coming back to treasury
    expect($transaction->type)->toBe('refund');

    // 3. Partner Balance Check
    $partner->refresh();
    // Supplier balance for cash return shouldn't change if no credit involved, 
    // unless they have an opening balance. Let's assume balance is what we owe.
    // In this system, cash transactions don't usually affect partner balance directly 
    // but the Partner::calculateBalance handles it based on transactions.
    expect((float)$partner->current_balance)->toBe(0.00);
});

test('it handles credit purchase return correctly', function () {
    $partner = Partner::factory()->supplier()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');
    
    // 0. Add initial stock so we can return it
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '100.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    // 1. Create a credit purchase first to have a balance
    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'subtotal' => '5000.00',
        'total' => '5000.00',
        'paid_amount' => '0.00',
        'remaining_amount' => '5000.00',
    ]);
    // Manually update partner balance for the invoice (since we didn't use fullPostPurchaseInvoice)
    app(TreasuryService::class)->updatePartnerBalance($partner->id);
    expect((float)$partner->refresh()->current_balance)->toBe(5000.00);

    // 2. Create a credit return
    $return = PurchaseReturn::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'subtotal' => '1000.00',
        'total' => '1000.00',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostPurchaseReturn($return, $this->treasury->id);

    // 3. Partner Balance Check
    $partner->refresh();
    // 5000 (owed) - 1000 (returned) = 4000
    expect((float)$partner->current_balance)->toBe(4000.00);

    // 4. Treasury Check (Should be none)
    $transaction = TreasuryTransaction::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->first();
    expect($transaction)->toBeNull();
});

test('it fails to post return if stock is insufficient', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');
    
    // Initial Stock: only 5 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 5,
        'cost_at_time' => '100.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10, // More than 5
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    expect(fn() => fullPostPurchaseReturn($return, $this->treasury->id))
        ->toThrow(Exception::class);
});

test('it fails to post return if total exceeds invoice total', function () {
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'total' => '1000.00',
        'status' => 'posted',
    ]);

    $return = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'total' => '1500.00', // More than 1000
        'status' => 'draft',
    ]);

    expect(fn() => fullPostPurchaseReturn($return, $this->treasury->id))
        ->toThrow(Exception::class, 'قيمة المرتجع تتجاوز قيمة الفاتورة المتبقية');
});

test('it fails to post return if item quantity exceeds invoice item quantity', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton']);
    
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'posted',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    $return = PurchaseReturn::factory()->create([
        'purchase_invoice_id' => $invoice->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 15, // More than 10
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1500.00',
    ]);

    expect(fn() => fullPostPurchaseReturn($return, $this->treasury->id))
        ->toThrow(Exception::class);
});

test('it handles small/large unit conversion correctly in returns', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], factor: 12);
    
    // Initial Stock: 2 cartons (24 pieces)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 24,
        'cost_at_time' => '100.00', // 100 per piece
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    // Return 1 carton @ 1200
    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_type' => 'large',
        'unit_cost' => '1200.00',
        'total' => '1200.00',
    ]);

    fullPostPurchaseReturn($return, $this->treasury->id);

    $movement = StockMovement::where('reference_type', 'purchase_return')->first();
    expect($movement->quantity)->toBe(-12);
    expect((float)$movement->cost_at_time)->toBe(100.00); // 1200 / 12 = 100
});

test('it updates avg_cost correctly when returning items with different cost', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '0.00');
    
    // Purchase 1: 10 @ 100 (Total 1000)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 10,
        'cost_at_time' => '100.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);
    
    // Purchase 2: 10 @ 200 (Total 2000)
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 10,
        'cost_at_time' => '200.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $this->stockService->updateProductAvgCost($product->id);
    expect((float)$product->refresh()->avg_cost)->toBe(150.00); // (1000 + 2000) / 20 = 150

    // Return 5 @ 200
    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_type' => 'small',
        'unit_cost' => '200.00',
        'total' => '1000.00',
    ]);

    fullPostPurchaseReturn($return, $this->treasury->id);

    // New Avg Cost = (Remaining Initial Cost) / (Remaining Quantity)
    // Initial Cost = 3000
    // Returned Cost = 5 * 200 = 1000
    // Remaining Cost = 2000
    // Remaining Qty = 20 - 5 = 15
    // Avg Cost = 2000 / 15 = 133.3333
    expect(abs((float)$product->refresh()->avg_cost - 133.3333))->toBeLessThan(0.0001);
});

test('it handles items with zero quantity in returns gracefully', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');
    
    // Initial Stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 50,
        'cost_at_time' => '100.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $return = PurchaseReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 0,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '0.00',
    ]);

    fullPostPurchaseReturn($return, $this->treasury->id);

    $movement = StockMovement::where('reference_type', 'purchase_return')->first();
    expect($movement->quantity)->toBe(0);
    
    $product->refresh();
    expect((float)$product->avg_cost)->toBe(100.00); // Should not change
});
