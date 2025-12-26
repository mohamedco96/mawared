<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\SalesReturn;
use App\Models\PurchaseReturn;
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
    public function getCurrentStock(string $warehouseId, string $productId, bool $lock = false): int
    {
        $query = StockMovement::where('warehouse_id', $warehouseId)
            ->where('product_id', $productId)
            ->withoutTrashed(); // Exclude soft-deleted movements

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->sum('quantity');
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
     * Get stock availability with descriptive message for validation
     */
    public function getStockValidationMessage(
        string $warehouseId,
        string $productId,
        int $requiredQuantity,
        string $unitType = 'small'
    ): array {
        $currentStock = $this->getCurrentStock($warehouseId, $productId);

        // Convert stock to display unit if needed
        $displayStock = $currentStock;
        $displayRequired = $requiredQuantity;

        if ($unitType === 'large' && $productId) {
            $product = Product::find($productId);
            if ($product && $product->factor > 1) {
                $displayStock = intval($currentStock / $product->factor);
                $displayRequired = intval($requiredQuantity / $product->factor);
            }
        }

        $isAvailable = $currentStock >= $requiredQuantity;

        return [
            'is_available' => $isAvailable,
            'current_stock' => $currentStock,
            'display_stock' => $displayStock,
            'message' => $isAvailable
                ? null
                : "المخزون المتاح: {$displayStock} وحدة، الكمية المطلوبة: {$displayRequired}"
        ];
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
    public function updateProductPrice(Product $product, ?string $newSellingPrice, string $unitType, ?string $newLargeSellingPrice = null): void
    {
        if ($newSellingPrice === null && $newLargeSellingPrice === null) {
            return;
        }

        DB::transaction(function () use ($product, $newSellingPrice, $unitType, $newLargeSellingPrice) {
            $updateData = [];

            // Update small unit price
            if ($newSellingPrice !== null) {
                $updateData['retail_price'] = $newSellingPrice;
            }

            // Update large unit price if provided
            if ($newLargeSellingPrice !== null && $product->large_unit_id) {
                $updateData['large_retail_price'] = $newLargeSellingPrice;
            }

            if (!empty($updateData)) {
                $product->update($updateData);
            }
        });
    }

    /**
     * Post a sales invoice - creates stock movements
     */
    public function postSalesInvoice(SalesInvoice $invoice): void
    {
        if (!$invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        DB::transaction(function () use ($invoice) {
            // Lock the invoice to prevent concurrent posting
            $invoice = SalesInvoice::lockForUpdate()->findOrFail($invoice->id);

            foreach ($invoice->items as $item) {
                $product = $item->product;

                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

                // Validate stock availability WITH LOCK inside transaction to prevent race conditions
                $currentStock = $this->getCurrentStock($invoice->warehouse_id, $product->id, true);

                if ($currentStock < $baseQuantity) {
                    throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
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
            throw new \Exception('الفاتورة ليست في حالة مسودة');
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
     * Post a sales return - creates stock movements (REVERSE of sale)
     */
    public function postSalesReturn(SalesReturn $return): void
    {
        if (!$return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        DB::transaction(function () use ($return) {
            foreach ($return->items as $item) {
                $product = $item->product;

                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

                // Create POSITIVE stock movement (sale_return)
                // REVERSE LOGIC: Sale removes stock (negative), return adds stock (positive)
                $this->recordMovement(
                    $return->warehouse_id,
                    $product->id,
                    'sale_return',
                    $baseQuantity, // POSITIVE for return
                    $product->avg_cost,
                    'sales_return',
                    $return->id
                );
            }
        });
    }

    /**
     * Post a purchase return - creates stock movements (REVERSE of purchase)
     */
    public function postPurchaseReturn(PurchaseReturn $return): void
    {
        if (!$return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        DB::transaction(function () use ($return) {
            foreach ($return->items as $item) {
                $product = $item->product;

                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

                // Validate stock availability (we're removing stock)
                if (!$this->validateStockAvailability($return->warehouse_id, $product->id, $baseQuantity)) {
                    throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
                }

                // Create NEGATIVE stock movement (purchase_return)
                // REVERSE LOGIC: Purchase adds stock (positive), return removes stock (negative)
                $this->recordMovement(
                    $return->warehouse_id,
                    $product->id,
                    'purchase_return',
                    -$baseQuantity, // NEGATIVE for return
                    $item->unit_cost,
                    'purchase_return',
                    $return->id
                );
            }

            // Update average cost for all products in this return
            foreach ($return->items as $item) {
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
            throw new \Exception('التسوية ليست في حالة مسودة');
        }

        DB::transaction(function () use ($adjustment) {
            $product = $adjustment->product;

            // Determine movement type and quantity direction based on adjustment type
            $quantity = abs($adjustment->quantity); // Always work with absolute value

            // Types that SUBTRACT from stock (negative quantity)
            $subtractionTypes = ['subtraction', 'damage', 'gift'];

            if (in_array($adjustment->type, $subtractionTypes)) {
                $movementType = 'adjustment_out';
                $quantity = -$quantity; // Make it negative for subtraction

                // Validate stock availability for subtraction
                if (!$this->validateStockAvailability($adjustment->warehouse_id, $product->id, abs($quantity))) {
                    throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
                }
            } else {
                // Types that ADD to stock (positive quantity): 'addition', 'opening', 'other'
                $movementType = 'adjustment_in';
                $quantity = $quantity; // Keep it positive
            }

            $this->recordMovement(
                $adjustment->warehouse_id,
                $product->id,
                $movementType,
                $quantity,
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

