<?php

namespace Tests\Helpers;

use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Unit;
use App\Models\Warehouse;

class TestHelpers
{
    /**
     * Create a product with dual units (small and large)
     */
    public static function createDualUnitProduct(
        ?Unit $smallUnit = null,
        ?Unit $largeUnit = null,
        int $factor = 12,
        string $avgCost = '50.00',
        string $retailPrice = '100.00'
    ): Product {
        $smallUnit = $smallUnit ?? Unit::factory()->create(['name' => 'قطعة', 'symbol' => 'قطعة']);
        $largeUnit = $largeUnit ?? Unit::factory()->create(['name' => 'كرتونة', 'symbol' => 'كرتونة']);

        return Product::factory()->create([
            'small_unit_id' => $smallUnit->id,
            'large_unit_id' => $largeUnit->id,
            'factor' => $factor,
            'avg_cost' => $avgCost,
            'retail_price' => $retailPrice,
            'large_retail_price' => (string)((float)$retailPrice * $factor),
        ]);
    }

    /**
     * Create a treasury with initial balance
     */
    public static function createFundedTreasury(string $amount = '1000000.0000'): Treasury
    {
        $treasury = Treasury::factory()->create([
            'name' => 'Test Treasury',
            'type' => 'cash',
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'income',
            'amount' => $amount,
            'description' => 'Initial Capital for Testing',
            'reference_type' => 'capital_injection',
            'reference_id' => null,
        ]);

        return $treasury;
    }

    /**
     * Create a draft sales invoice with items
     */
    public static function createDraftSalesInvoice(
        ?Warehouse $warehouse = null,
        ?Partner $partner = null,
        array $items = []
    ): SalesInvoice {
        $warehouse = $warehouse ?? Warehouse::factory()->create();
        $partner = $partner ?? Partner::factory()->customer()->create();

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $partner->id,
            'status' => 'draft',
        ]);

        // Add items if provided
        if (!empty($items)) {
            foreach ($items as $itemData) {
                $invoice->items()->create($itemData);
            }
        }

        return $invoice->fresh(['items.product']);
    }

    /**
     * Create units for testing
     */
    public static function createUnits(): array
    {
        $pieceUnit = Unit::factory()->create(['name' => 'قطعة', 'symbol' => 'قطعة']);
        $cartonUnit = Unit::factory()->create(['name' => 'كرتونة', 'symbol' => 'كرتونة']);

        return ['piece' => $pieceUnit, 'carton' => $cartonUnit];
    }
}
