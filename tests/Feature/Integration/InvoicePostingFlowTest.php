<?php

use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
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

test('it completes full sales invoice posting flow', function () {
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

    // Create draft invoice
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

    // Post invoice
    $this->stockService->postSalesInvoice($invoice);
    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
    $invoice->update(['status' => 'posted']);

    // Verify stock deducted
    $stock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($stock)->toBe(50); // 100 - 50

    // Verify treasury transaction (if cash invoice)
    $invoice->refresh();
    if ($invoice->paid_amount > 0) {
        $transaction = TreasuryTransaction::where('reference_type', 'sales_invoice')
            ->where('reference_id', $invoice->id)
            ->first();
        expect($transaction)->not->toBeNull();
    }

    // Verify partner balance updated
    $customer->refresh();
    if ($invoice->payment_method === 'credit') {
        expect((float)$customer->current_balance)->toBeGreaterThan(0.0);
    }
});

test('it completes full purchase invoice posting flow', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '0.00'
    );

    $supplier = Partner::factory()->supplier()->create([
        'opening_balance' => '0.0000',
    ]);

    // Create draft purchase invoice
    $invoice = PurchaseInvoice::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'partner_id' => $supplier->id,
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

    // Post invoice
    $this->stockService->postPurchaseInvoice($invoice);
    $this->treasuryService->postPurchaseInvoice($invoice, $this->treasury->id);
    $invoice->update(['status' => 'posted']);

    // Verify stock added
    $stock = $this->stockService->getCurrentStock($this->warehouse->id, $product->id);
    expect($stock)->toBe(100);

    // Verify avg_cost updated
    $product->refresh();
    expect((float)$product->avg_cost)->toBe(50.00);

    // Verify treasury transaction (if cash invoice)
    $invoice->refresh();
    if ($invoice->paid_amount > 0) {
        $transaction = TreasuryTransaction::where('reference_type', 'purchase_invoice')
            ->where('reference_id', $invoice->id)
            ->first();
        expect($transaction)->not->toBeNull();
    }

    // Verify partner balance updated
    $supplier->refresh();
    if ($invoice->payment_method === 'credit') {
        expect((float)$supplier->current_balance)->toBeLessThan(0.0);
    }
});
