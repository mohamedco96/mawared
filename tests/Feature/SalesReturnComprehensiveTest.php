<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TreasuryService;
use App\Services\CommissionService;
use Illuminate\Support\Facades\DB;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->treasuryService = app(TreasuryService::class);
    $this->commissionService = app(CommissionService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->treasury = TestHelpers::createFundedTreasury('100000.0000');
    $this->units = TestHelpers::createUnits();
});

/**
 * HELPER: Simulate the full posting process as done in Filament EditSalesReturn::afterSave
 */
function fullPostSalesReturn($return, $treasuryId) {
    DB::transaction(function () use ($return, $treasuryId) {
        $return->load('items.product');
        
        // Temporarily set to draft for service validation
        $return->status = 'draft';
        
        app(StockService::class)->postSalesReturn($return);
        app(TreasuryService::class)->postSalesReturn($return, $treasuryId);
        
        $return->status = 'posted';
        $return->saveQuietly();
        
        // Recalculate partner balance AFTER status is posted
        if ($return->partner_id) {
            app(TreasuryService::class)->updatePartnerBalance($return->partner_id);
        }
    });
}

test('it handles cash sales return correctly', function () {
    $partner = Partner::factory()->customer()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    $return = SalesReturn::factory()->create([
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
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostSalesReturn($return, $this->treasury->id);

    // 1. Stock Check (Positive movement for return)
    $movement = StockMovement::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();
    expect($movement->quantity)->toBe(10);
    expect((float)$movement->cost_at_time)->toBe(50.00);

    // 2. Treasury Check (Money leaves treasury for refund)
    $transaction = TreasuryTransaction::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();
    expect((float)$transaction->amount)->toBe(-1000.00);

    // 3. Partner Balance Check (Cash return doesn't affect debt)
    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(0.00);
});

test('it handles credit sales return correctly', function () {
    $partner = Partner::factory()->customer()->create(['opening_balance' => 0]);
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    // 1. Create a credit sale first to have a balance
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'subtotal' => '5000.00',
        'total' => '5000.00',
        'paid_amount' => '0.00',
        'remaining_amount' => '5000.00',
    ]);
    app(TreasuryService::class)->updatePartnerBalance($partner->id);
    expect((float)$partner->refresh()->current_balance)->toBe(5000.00);

    // 2. Create a credit return
    $return = SalesReturn::factory()->create([
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
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostSalesReturn($return, $this->treasury->id);

    // 3. Partner Balance Check
    $partner->refresh();
    // 5000 (owed) - 1000 (returned) = 4000
    expect((float)$partner->current_balance)->toBe(4000.00);

    // 4. Treasury Check (Should be none)
    $transaction = TreasuryTransaction::where('reference_id', $return->id)->first();
    expect($transaction)->toBeNull();
});

test('it reverses commission correctly during sales return', function () {
    $salesPerson = \App\Models\User::factory()->create();
    $partner = Partner::factory()->customer()->create();
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    // 1. Create posted invoice with paid commission
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'sales_person_id' => $salesPerson->id,
        'commission_rate' => '10.00',
        'commission_amount' => '500.00', // 10% of 5000
        'commission_paid' => true,
        'status' => 'posted',
        'subtotal' => '5000.00',
        'total' => '5000.00',
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 50,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '5000.00',
    ]);

    // 2. Create return for partial amount
    $return = SalesReturn::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'partner_id' => $partner->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
        'total' => '1000.00', // 20% of invoice
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    fullPostSalesReturn($return, $this->treasury->id);

    // 3. Commission Check
    // Proportional reversal: 20% of 500 = 100
    $transaction = TreasuryTransaction::where('reference_type', 'sales_return')
        ->where('type', \App\Enums\TransactionType::COMMISSION_REVERSAL->value)
        ->first();
    
    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(100.00); // Money returns to treasury

    $invoice->refresh();
    expect((float)$invoice->commission_amount)->toBe(400.00);
});

test('it fails to post return if total exceeds invoice total', function () {
    $invoice = SalesInvoice::factory()->create([
        'total' => '1000.00',
        'status' => 'posted',
    ]);

    $return = SalesReturn::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'total' => '1200.00',
        'status' => 'draft',
    ]);

    expect(fn() => fullPostSalesReturn($return, $this->treasury->id))
        ->toThrow(Exception::class, 'لا يمكن تأكيد المرتجع: قيمة المرتجع تتجاوز قيمة الفاتورة المتبقية');
});

test('it updates original invoice COGS correctly', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');

    $invoice = SalesInvoice::factory()->create([
        'status' => 'posted',
        'total' => '1000.00',
        'cost_total' => '500.00', // 10 pieces * 50.00
    ]);

    $invoice->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '1000.00',
    ]);

    $return = SalesReturn::factory()->create([
        'sales_invoice_id' => $invoice->id,
        'status' => 'draft',
        'total' => '400.00',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 4,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '400.00',
    ]);

    fullPostSalesReturn($return, $this->treasury->id);

    $invoice->refresh();
    // Return 4 pieces. Original COGS was 500. Reversal = 4 * 50 = 200.
    // New COGS = 500 - 200 = 300.
    expect((float)$invoice->cost_total)->toBe(300.00);
});

test('it handles items with zero quantity in returns gracefully', function () {
    $product = TestHelpers::createDualUnitProduct($this->units['piece'], $this->units['carton'], avgCost: '50.00');
    
    $return = SalesReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'status' => 'draft',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 0,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'total' => '0.00',
    ]);

    fullPostSalesReturn($return, $this->treasury->id);

    $movement = StockMovement::where('reference_type', 'sales_return')->first();
    expect($movement->quantity)->toBe(0);
});
