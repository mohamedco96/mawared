<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\TreasuryTransaction;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->treasuryService = app(TreasuryService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->treasury = TestHelpers::createFundedTreasury('100000.0000');
    $this->units = TestHelpers::createUnits();
});

test('it completes cash return flow', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $customer = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'initial-purchase',
    ]);

    // Post sales invoice first
    $invoice = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        partner: $customer,
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

    $this->stockService->postSalesInvoice($invoice);
    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
    $invoice->update(['status' => 'posted']);

    $initialStock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);

    // Post cash return
    $return = SalesReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $customer->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '1000.0000',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'discount' => '0.00',
        'total' => '1000.00',
    ]);

    $this->stockService->postSalesReturn($return);
    $this->treasuryService->postSalesReturn($return, $this->treasury->id);
    $return->update(['status' => 'posted']);

    // Verify stock restored
    $finalStock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($finalStock)->toBe($initialStock + 10);

    // Verify treasury refund
    $finalBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect((float)$finalBalance)->toBe((float)$initialBalance - 1000.0);

    // Verify treasury transaction created
    $transaction = TreasuryTransaction::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();
    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(-1000.0);
});

test('it completes credit return flow', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    $customer = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    // Add initial stock
    StockMovement::create([
        'warehouse_id' => $this->warehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'initial-purchase-2',
    ]);

    // Post sales invoice first
    $invoice = TestHelpers::createDraftSalesInvoice(
        warehouse: $this->warehouse,
        partner: $customer,
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

    $this->stockService->postSalesInvoice($invoice);
    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
    $invoice->update(['status' => 'posted']);

    $this->treasuryService->updatePartnerBalance($customer->id);
    $customer->refresh();
    $initialBalance = (float)$customer->current_balance;

    // Post credit return
    $return = SalesReturn::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $customer->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '1000.0000',
    ]);

    $return->items()->create([
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_type' => 'small',
        'unit_price' => '100.00',
        'discount' => '0.00',
        'total' => '1000.00',
    ]);

    $this->stockService->postSalesReturn($return);
    $this->treasuryService->postSalesReturn($return, $this->treasury->id);
    $return->update(['status' => 'posted']);

    // Verify stock restored
    $stock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($stock)->toBeGreaterThan(0);

    // Verify NO treasury transaction (credit return)
    $transaction = TreasuryTransaction::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();
    expect($transaction)->toBeNull();

    // Verify partner balance updated
    $customer->refresh();
    expect((float)$customer->current_balance)->toBe($initialBalance - 1000.0);
});
