<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\WarehouseTransfer;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Record a stock movement (only called when invoice/adjustment is posted)
     */
    public function recordMovement(
        string $warehouseId,
        string $productId,
        string $type,
        int $quantity,
        string $costAtTime,
        string $referenceType,
        string $referenceId,
        ?string $notes = null
    ): StockMovement {
        return DB::transaction(function () use ($warehouseId, $productId, $type, $quantity, $costAtTime, $referenceType, $referenceId, $notes) {
            return StockMovement::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'type' => $type,
                'quantity' => $quantity,
                'cost_at_time' => $costAtTime,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Convert quantity to base unit (small_unit)
     */
    public function convertToBaseUnit(Product $product, int $quantity, string $unitType): int
    {
        return $product->convertToBaseUnit($quantity, $unitType);
    }

    /**
     * Get current stock for a product in a warehouse
     */
    public function getCurrentStock(string $warehouseId, string $productId): int
    {
        return StockMovement::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->sum('quantity');
    }

    /**
     * Validate stock availability before sale
     */
    public function validateStockAvailability(string $warehouseId, string $productId, int $requiredQuantity): bool
    {
        $currentStock = $this->getCurrentStock($warehouseId, $productId);
        return $currentStock >= $requiredQuantity;
    }

    /**
     * Update product average cost after purchase
     */
    public function updateProductAvgCost(string $productId): void
    {
        DB::transaction(function () use ($productId) {
            $product = Product::findOrFail($productId);
            
            // Calculate weighted average cost from purchase movements
            $purchaseMovements = StockMovement::where('product_id', $productId)
                ->where('type', 'purchase')
                ->where('quantity', '>', 0)
                ->get();

            if ($purchaseMovements->isEmpty()) {
                return;
            }

            $totalCost = 0;
            $totalQuantity = 0;

            foreach ($purchaseMovements as $movement) {
                $totalCost += $movement->cost_at_time * $movement->quantity;
                $totalQuantity += $movement->quantity;
            }

            if ($totalQuantity > 0) {
                $avgCost = $totalCost / $totalQuantity;
                $product->update(['avg_cost' => $avgCost]);
            }
        });
    }

    /**
     * Update product selling prices when new_selling_price is set
     */
    public function updateProductPrice(Product $product, ?string $newSellingPrice, string $unitType): void
    {
        if ($newSellingPrice === null) {
            return;
        }

        DB::transaction(function () use ($product, $newSellingPrice, $unitType) {
            if ($unitType === 'small') {
                $product->update([
                    'retail_price' => $newSellingPrice,
                ]);
            } elseif ($unitType === 'large' && $product->large_unit_id) {
                $product->update([
                    'large_retail_price' => $newSellingPrice,
                ]);
            }
        });
    }

    /**
     * Post a sales invoice - creates stock movements
     */
    public function postSalesInvoice(SalesInvoice $invoice): void
    {
        if (!$invoice->isDraft()) {
            throw new \Exception('Invoice is not in draft status');
        }

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->items as $item) {
                $product = $item->product;
                
                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);
                
                // Validate stock availability
                if (!$this->validateStockAvailability($invoice->warehouse_id, $product->id, $baseQuantity)) {
                    throw new \Exception("Insufficient stock for product: {$product->name}");
                }

                // Create negative stock movement (sale)
                $this->recordMovement(
                    $invoice->warehouse_id,
                    $product->id,
                    'sale',
                    -$baseQuantity, // Negative for sale
                    $product->avg_cost,
                    'sales_invoice',
                    $invoice->id
                );
            }
        });
    }

    /**
     * Post a purchase invoice - creates stock movements and updates costs
     */
    public function postPurchaseInvoice(PurchaseInvoice $invoice): void
    {
        if (!$invoice->isDraft()) {
            throw new \Exception('Invoice is not in draft status');
        }

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->items as $item) {
                $product = $item->product;
                
                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);
                
                // Create positive stock movement (purchase)
                $this->recordMovement(
                    $invoice->warehouse_id,
                    $product->id,
                    'purchase',
                    $baseQuantity, // Positive for purchase
                    $item->unit_cost,
                    'purchase_invoice',
                    $invoice->id
                );

                // Update product price if new_selling_price is set
                if ($item->new_selling_price !== null) {
                    $this->updateProductPrice($product, $item->new_selling_price, $item->unit_type);
                }
            }

            // Update average cost for all products in this invoice
            foreach ($invoice->items as $item) {
                $this->updateProductAvgCost($item->product_id);
            }
        });
    }

    /**
     * Post a stock adjustment - creates stock movements
     */
    public function postStockAdjustment(StockAdjustment $adjustment): void
    {
        if (!$adjustment->isDraft()) {
            throw new \Exception('Stock adjustment is not in draft status');
        }

        DB::transaction(function () use ($adjustment) {
            $product = $adjustment->product;
            $movementType = $adjustment->type === 'damage' || $adjustment->type === 'gift' 
                ? 'adjustment_out' 
                : 'adjustment_in';

            $this->recordMovement(
                $adjustment->warehouse_id,
                $product->id,
                $movementType,
                $adjustment->quantity, // Can be positive or negative
                $product->avg_cost,
                'stock_adjustment',
                $adjustment->id,
                $adjustment->notes
            );
        });
    }

    /**
     * Post a warehouse transfer - creates dual stock movements
     */
    public function postWarehouseTransfer(WarehouseTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $product = $item->product;
                
                // Negative movement from source warehouse
                $this->recordMovement(
                    $transfer->from_warehouse_id,
                    $product->id,
                    'transfer',
                    -$item->quantity, // Negative for out
                    $product->avg_cost,
                    'warehouse_transfer',
                    $transfer->id
                );

                // Positive movement to destination warehouse
                $this->recordMovement(
                    $transfer->to_warehouse_id,
                    $product->id,
                    'transfer',
                    $item->quantity, // Positive for in
                    $product->avg_cost,
                    'warehouse_transfer',
                    $transfer->id
                );
            }
        });
    }
}

