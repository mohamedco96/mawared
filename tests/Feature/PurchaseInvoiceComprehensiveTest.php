<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->treasuryService = app(TreasuryService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->units = TestHelpers::createUnits();
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
});

/**
 * HELPER: Simulate the full posting process as done in Filament EditPurchaseInvoice::afterSave
 */
function fullPostPurchaseInvoice($invoice, $treasuryId) {
    DB::transaction(function () use ($invoice, $treasuryId) {
        $invoice->load('items.product');
        app(StockService::class)->postPurchaseInvoice($invoice);
        app(TreasuryService::class)->postPurchaseInvoice($invoice, $treasuryId);
        
        $invoice->status = 'posted';
        $invoice->saveQuietly();
        
        // CRITICAL: Recalculate partner balance AFTER the status is set to 'posted'
        if ($invoice->partner_id) {
            app(TreasuryService::class)->updatePartnerBalance($invoice->partner_id);
        }
    });
}

test('it updates wholesale prices correctly during purchase posting', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        retailPrice: '100.00',
        wholesalePrice: '90.00'
    );

    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '50.00',
        'discount' => '0.00',
        'total' => '500.00',
        'wholesale_price' => '95.00',
        'large_wholesale_price' => '1100.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $product->refresh();
    expect((float)$product->wholesale_price)->toBe(95.00);
    expect((float)$product->large_wholesale_price)->toBe(1100.00);
});

test('it fails to post cash purchase when treasury balance is insufficient', function () {
    $partner = Partner::factory()->supplier()->create();
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton']);
    
    // Create an invoice for 20,000 but treasury only has 10,000
    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '20000.0000',
        'paid_amount' => '20000.0000',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 200,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '20000.0000',
    ]);

    // This should fail because TreasuryService::recordTransaction checks balance
    expect(fn() => fullPostPurchaseInvoice($invoice, $this->treasury->id))
        ->toThrow(Exception::class, 'لا يمكن إتمام العملية: الرصيد المتاح غير كافٍ في الخزينة');

    // Verify atomicity: stock should NOT have been updated (rolled back)
    $product->refresh();
    expect(StockMovement::where('product_id', $product->id)->count())->toBe(0);
});

test('it handles standard cash purchase correctly', function () {
    $partner = Partner::factory()->supplier()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');

    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'paid_amount' => '1000.00',
        'remaining_amount' => '0.00',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostPurchaseInvoice($invoice, $this->treasury->id);

    // 1. Treasury Check
    $transaction = TreasuryTransaction::where('reference_id', $invoice->id)->first();
    expect((float)$transaction->amount)->toBe(-1000.00);

    // 2. Partner Balance Check (Cash purchase should not increase debt)
    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(0.00);

    // 3. Stock Check
    $movement = StockMovement::where('reference_id', $invoice->id)->first();
    expect($movement->quantity)->toBe(10);
});

test('it handles credit purchase with partial payment correctly', function () {
    $partner = Partner::factory()->supplier()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');

    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'paid_amount' => '300.00',
        'remaining_amount' => '700.00',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostPurchaseInvoice($invoice, $this->treasury->id);

    // 1. Treasury Check (Transaction for paid amount only)
    $transaction = TreasuryTransaction::where('reference_id', $invoice->id)->first();
    expect((float)$transaction->amount)->toBe(-300.00);

    // 2. Partner Balance Check (Debt increases by remaining amount)
    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(700.00);
});


test('it handles purchase invoice with discount correctly', function () {
    $partner = Partner::factory()->supplier()->create();
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');

    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'subtotal' => '1000.00',
        'discount' => '100.00',
        'total' => '900.00',
        'payment_method' => 'cash',
        'paid_amount' => '900.00',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostPurchaseInvoice($invoice, $this->treasury->id);

    // Verify treasury transaction is for the total (900), not subtotal (1000)
    $transaction = TreasuryTransaction::where('reference_type', 'purchase_invoice')
        ->where('reference_id', $invoice->id)
        ->first();
    
    expect((float)$transaction->amount)->toBe(-900.00);

    // Verify stock movement cost is still 100.00 (discount is at invoice level, usually not affecting individual item cost in this system's current logic)
    // Wait, let's check if the system should distribute discount to items for avg cost.
    // In StockService::postPurchaseInvoice, it uses $item->unit_cost.
    $movement = StockMovement::where('reference_type', 'purchase_invoice')->first();
    expect((float)$movement->cost_at_time)->toBe(100.00);
});

test('it handles items with zero quantity gracefully', function () {
     $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '0.00');
    
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 0,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '0.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    // Should create a movement with 0 quantity (or maybe skip it? recordMovement creates it)
    $movement = StockMovement::where('product_id', $product->id)->first();
    expect($movement->quantity)->toBe(0);
    
    // Avg cost should not be updated for 0 quantity items (based on StockService logic)
    $product->refresh();
    expect((float)$product->avg_cost)->toBe(0.00);
});

test('it recalculates partner balance correctly for multiple credit purchases', function () {
    $partner = Partner::factory()->supplier()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton']);

    // First Invoice
    $invoice1 = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '1000.00',
        'paid_amount' => '0.00',
        'remaining_amount' => '1000.00',
    ]);
    $invoice1->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);
    fullPostPurchaseInvoice($invoice1, $this->treasury->id);

    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(1000.0);

    // Second Invoice (Partial Payment)
    $invoice2 = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '2000.00',
        'paid_amount' => '500.00',
        'remaining_amount' => '1500.00',
    ]);
    $invoice2->items()->create([
        'product_id' => $product->id,
        'quantity' => 20,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '2000.00',
    ]);
    fullPostPurchaseInvoice($invoice2, $this->treasury->id);

    $partner->refresh();
    // 1000 (invoice1) + 1500 (invoice2 remaining) = 2500
    expect((float)$partner->current_balance)->toBe(2500.0);
});

test('it prevents posting if product does not exist', function () {
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    // Disable integrity checks temporarily to test service behavior with invalid ID
    // or just expect the QueryException if that's how it fails.
    try {
        $invoice->items()->create([
            'product_id' => 'non-existent-ulid',
            'quantity' => 10,
            'unit_type' => 'small',
            'unit_cost' => '100.00',
            'total' => '1000.00',
        ]);
    } catch (\Illuminate\Database\QueryException $e) {
        // This is expected due to FK constraint
        expect(true)->toBe(true);
        return;
    }

    expect(fn() => $this->stockService->postPurchaseInvoice($invoice))
        ->toThrow(Exception::class);
});

test('it handles purchase invoice with zero cost items (free samples)', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '100.00');
    
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '0.00',
        'total' => '0.00',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $product->refresh();
    // Avg cost should be diluted: (OldStock * 100 + 10 * 0) / (OldStock + 10)
    // Here we don't have OldStock in this test's context yet, let's check dilution.
    // If it's the first purchase: (10 * 0) / 10 = 0
    expect((float)$product->avg_cost)->toBe(0.00);

    $movement = StockMovement::where('product_id', $product->id)->first();
    expect((float)$movement->cost_at_time)->toBe(0.00);
});

test('it handles purchase invoice where discount equals subtotal (zero total)', function () {
    $partner = Partner::factory()->supplier()->create();
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton']);

    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'subtotal' => '1000.00',
        'discount' => '1000.00',
        'total' => '0.00',
        'payment_method' => 'cash',
        'paid_amount' => '0.00',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_cost' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostPurchaseInvoice($invoice, $this->treasury->id);

    // Should not create treasury transaction for 0 amount
    $transaction = TreasuryTransaction::where('reference_id', $invoice->id)->first();
    expect($transaction)->toBeNull();

    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(0.00);
});


test('it handles very large quantities correctly', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton']);
    
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 1000000, // 1 million
        'unit_type' => 'small',
        'unit_cost' => '1.0000',
        'total' => '1000000.0000',
    ]);

    $this->stockService->postPurchaseInvoice($invoice);

    $movement = StockMovement::where('product_id', $product->id)->first();
    expect($movement->quantity)->toBe(1000000);
});
