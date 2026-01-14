<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Models\WarehouseTransferItem;
use App\Services\StockService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->stockService = app(StockService::class);
    $this->fromWarehouse = Warehouse::factory()->create(['name' => 'Warehouse A']);
    $this->toWarehouse = Warehouse::factory()->create(['name' => 'Warehouse B']);
    $this->units = TestHelpers::createUnits();
    $this->user = \App\Models\User::factory()->create();
});

test('it completes warehouse transfer flow', function () {
    $product = TestHelpers::createDualUnitProduct(
        $this->units['piece'],
        $this->units['carton'],
        factor: 12,
        avgCost: '50.00'
    );

    // Add stock to source warehouse
    StockMovement::create([
        'warehouse_id' => $this->fromWarehouse->id,
        'product_id' => $product->id,
        'type' => 'purchase',
        'quantity' => 100,
        'cost_at_time' => '50.00',
        'reference_type' => 'purchase_invoice',
        'reference_id' => 'test-purchase',
    ]);

    $initialFromStock = $this->stockService->getCurrentStock($this->fromWarehouse->id, $product->id);
    $initialToStock = $this->stockService->getCurrentStock($this->toWarehouse->id, $product->id);

    // Create transfer
    $transfer = WarehouseTransfer::create([
        'transfer_number' => 'TRF-001',
        'from_warehouse_id' => $this->fromWarehouse->id,
        'to_warehouse_id' => $this->toWarehouse->id,
        'notes' => 'Transfer test',
        'created_by' => $this->user->id,
    ]);

    WarehouseTransferItem::create([
        'warehouse_transfer_id' => $transfer->id,
        'product_id' => $product->id,
        'quantity' => 50,
    ]);

    // Post transfer
    $this->stockService->postWarehouseTransfer($transfer);

    // Verify source warehouse stock decreased
    $finalFromStock = $this->stockService->getCurrentStock($this->fromWarehouse->id, $product->id);
    expect($finalFromStock)->toBe($initialFromStock - 50);

    // Verify destination warehouse stock increased
    $finalToStock = $this->stockService->getCurrentStock($this->toWarehouse->id, $product->id);
    expect($finalToStock)->toBe($initialToStock + 50);

    // Verify both movements created
    $outMovement = StockMovement::where('reference_type', 'warehouse_transfer')
        ->where('reference_id', $transfer->id)
        ->where('warehouse_id', $this->fromWarehouse->id)
        ->first();
    expect($outMovement)->not->toBeNull();
    expect($outMovement->quantity)->toBe(-50);

    $inMovement = StockMovement::where('reference_type', 'warehouse_transfer')
        ->where('reference_id', $transfer->id)
        ->where('warehouse_id', $this->toWarehouse->id)
        ->first();
    expect($inMovement)->not->toBeNull();
    expect($inMovement->quantity)->toBe(50);
});
