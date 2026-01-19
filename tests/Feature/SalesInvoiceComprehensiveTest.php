<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Warehouse;
use App\Models\Installment;
use App\Services\StockService;
use App\Services\TreasuryService;
use App\Services\InstallmentService;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->treasuryService = app(TreasuryService::class);
    $this->installmentService = app(InstallmentService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
    $this->units = TestHelpers::createUnits();
});

/**
 * HELPER: Simulate the full posting process as done in Filament EditSalesInvoice::afterSave
 */
function fullPostSalesInvoice($invoice, $treasuryId) {
    DB::transaction(function () use ($invoice, $treasuryId) {
        $invoice->load('items.product');
        
        // Temporarily set to draft for service validation
        $invoice->status = 'draft';
        
        app(StockService::class)->postSalesInvoice($invoice);
        app(TreasuryService::class)->postSalesInvoice($invoice, $treasuryId);
        
        $invoice->status = 'posted';
        $invoice->saveQuietly();
        
        // Recalculate partner balance AFTER status is posted
        if ($invoice->partner_id) {
            app(TreasuryService::class)->updatePartnerBalance($invoice->partner_id);
        }

        // Generate installments if plan is enabled
        if ($invoice->has_installment_plan) {
            app(InstallmentService::class)->generateInstallmentSchedule($invoice);
        }
    });
}

test('it handles cash sales invoice correctly', function () {
    $partner = Partner::factory()->customer()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    // Add initial stock: 100 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $invoice = SalesInvoice::factory()->create([
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
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostSalesInvoice($invoice, $this->treasury->id);

    // 1. Stock Check
    $movement = StockMovement::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();
    expect($movement->quantity)->toBe(-10);
    expect((float)$movement->cost_at_time)->toBe(50.00); // Should be avg_cost

    // 2. Treasury Check
    $transaction = TreasuryTransaction::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();
    expect((float)$transaction->amount)->toBe(1000.00); // Money added to treasury

    // 3. Partner Balance Check
    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(0.00); // Cash sale doesn't affect debt

    // 4. COGS Check
    $invoice->refresh();
    expect((float)$invoice->cost_total)->toBe(500.00); // 10 * 50.00
});

test('it handles credit sales invoice with partial payment correctly', function () {
    $partner = Partner::factory()->customer()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'subtotal' => '1000.00',
        'total' => '1000.00',
        'paid_amount' => '200.00',
        'remaining_amount' => '800.00',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostSalesInvoice($invoice, $this->treasury->id);

    // 1. Treasury Check (Only paid amount)
    $transaction = TreasuryTransaction::where('reference_id', $invoice->id)->first();
    expect((float)$transaction->amount)->toBe(200.00);

    // 2. Partner Balance Check (Debtor balance increases)
    $partner->refresh();
    // In this system, customer balance is negative if they owe money? 
    // Let's check Partner::calculateBalance logic
    // For customers: current_balance = opening_balance + salesInvoices(remaining) - payments - returns
    // So if they owe 800, balance should be 800.
    expect((float)$partner->current_balance)->toBe(800.00);
});

test('it fails to post sales invoice if stock is insufficient', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');
    
    // Initial stock: only 5 pieces
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 5,
        'cost_at_time' => '50.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $invoice = SalesInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10, // More than 5
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    expect(fn() => fullPostSalesInvoice($invoice, $this->treasury->id))
        ->toThrow(Exception::class, "المخزون غير كافٍ للمنتج: {$product->name}");
});

test('it generates installment schedule correctly', function () {
    $partner = Partner::factory()->customer()->create();
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '1200.00',
        'paid_amount' => '0.00',
        'remaining_amount' => '1200.00',
        'has_installment_plan' => true,
        'installment_months' => 4,
        'installment_start_date' => now()->addMonth(),
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 12,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '1200.00',
    ]);

    fullPostSalesInvoice($invoice, $this->treasury->id);

    // Verify installments
    $installments = Installment::where('sales_invoice_id', $invoice->id)->get();
    expect($installments->count())->toBe(4);
    expect((float)$installments->first()->amount)->toBe(300.00); // 1200 / 4
});

test('it handles items with zero quantity gracefully', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');
    
    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $invoice = SalesInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 0,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '0.00',
    ]);

    fullPostSalesInvoice($invoice, $this->treasury->id);

    $movement = StockMovement::where('reference_type', 'sales_invoice')->first();
    expect($movement->quantity)->toBe(0);
});

test('it handles sales invoice with 100% discount correctly', function () {
    $partner = Partner::factory()->customer()->create();
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $invoice = SalesInvoice::factory()->create([
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
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostSalesInvoice($invoice, $this->treasury->id);

    // Should not create treasury transaction for 0 amount
    $transaction = TreasuryTransaction::where('reference_id', $invoice->id)->first();
    expect($transaction)->toBeNull();

    // But stock should be deducted
    $movement = StockMovement::where('reference_id', $invoice->id)->first();
    expect($movement->quantity)->toBe(-10);
});

test('it handles very large quantities in sales correctly', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');
    
    // Add initial stock: large amount
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 2000000,
        'cost_at_time' => '50.00',
        'reference_type' => 'manual',
        'reference_id' => (string) \Illuminate\Support\Str::ulid(),
    ]);

    $invoice = SalesInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 1000000,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '100000000.00',
    ]);

    fullPostSalesInvoice($invoice, $this->treasury->id);

    $movement = StockMovement::where('reference_type', 'sales_invoice')->first();
    expect($movement->quantity)->toBe(-1000000);
});
