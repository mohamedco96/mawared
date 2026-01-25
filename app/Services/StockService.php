<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
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
        \Log::info('StockService::recordMovement called', [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'transaction_level' => DB::transactionLevel(),
        ]);

        $execute = function () use ($warehouseId, $productId, $type, $quantity, $costAtTime, $referenceType, $referenceId, $notes) {
            \Log::info('Creating stock movement record', [
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'transaction_level' => DB::transactionLevel(),
            ]);

            $movement = StockMovement::create([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'type' => $type,
                'quantity' => $quantity,
                'cost_at_time' => $costAtTime,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);

            \Log::info('Stock movement record created', [
                'movement_id' => $movement->id,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'transaction_level' => DB::transactionLevel(),
            ]);

            return $movement;
        };

        // Always wrap in transaction to ensure atomicity and handle nested transactions correctly
        return DB::transaction($execute);
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
                : "المخزون المتاح: {$displayStock} وحدة، الكمية المطلوبة: {$displayRequired}",
        ];
    }

    /**
     * Update product average cost after purchase
     *
     * CRITICAL FIXES APPLIED:
     * 1. Uses BC Math for all financial calculations (CRITICAL-01)
     * 2. Uses lockForUpdate() to prevent race conditions (CRITICAL-02)
     * 3. Preserves last known avg_cost when quantity is zero (CRITICAL-03)
     */
    public function updateProductAvgCost(string $productId): void
    {
        $execute = function () use ($productId) {
            // CRITICAL-02 FIX: Lock product to prevent concurrent updates
            $product = Product::lockForUpdate()->findOrFail($productId);

            // Calculate weighted average cost from purchase and purchase_return movements
            // Also lock movements to ensure consistent calculation
            $purchaseMovements = StockMovement::where('product_id', $productId)
                ->whereIn('type', ['purchase', 'purchase_return'])
                ->where('quantity', '!=', 0)
                ->lockForUpdate()
                ->get();

            if ($purchaseMovements->isEmpty()) {
                return;
            }

            // CRITICAL-01 FIX: Use BC Math for precise financial calculations
            $totalCost = '0';
            $totalQuantity = '0';

            foreach ($purchaseMovements as $movement) {
                // quantity can be negative for purchase_return
                // cost_at_time * quantity
                $movementCost = bcmul((string) $movement->cost_at_time, (string) $movement->quantity, 4);
                $totalCost = bcadd($totalCost, $movementCost, 4);
                $totalQuantity = bcadd($totalQuantity, (string) $movement->quantity, 4);
            }

            // CRITICAL-03 FIX: Only update if quantity > 0, otherwise preserve last known cost
            if (bccomp($totalQuantity, '0', 4) > 0) {
                $avgCost = bcdiv($totalCost, $totalQuantity, 4);

                \Log::info('Product avg_cost updated', [
                    'product_id' => $productId,
                    'old_avg_cost' => $product->avg_cost,
                    'new_avg_cost' => $avgCost,
                    'total_cost' => $totalCost,
                    'total_quantity' => $totalQuantity,
                    'movement_count' => $purchaseMovements->count(),
                ]);

                $product->update(['avg_cost' => $avgCost]);
            }
            // If totalQuantity <= 0, do NOT update avg_cost - preserve the last known value
            // This maintains historical cost data for GAAP compliance
        };

        // Wrap in transaction to ensure atomicity
        DB::transaction($execute);
    }

    /**
     * Update product selling prices when new_selling_price is set
     */
    public function updateProductPrice(
        Product $product,
        ?string $newSellingPrice,
        string $unitType,
        ?string $newLargeSellingPrice = null,
        ?string $wholesalePrice = null,
        ?string $largeWholesalePrice = null
    ): void {
        if ($newSellingPrice === null && $newLargeSellingPrice === null && $wholesalePrice === null && $largeWholesalePrice === null) {
            return;
        }

        $execute = function () use ($product, $newSellingPrice, $newLargeSellingPrice, $wholesalePrice, $largeWholesalePrice) {
            $updateData = [];

            // Update small unit retail price
            if ($newSellingPrice !== null) {
                $updateData['retail_price'] = $newSellingPrice;
            }

            // Update large unit retail price if provided
            if ($newLargeSellingPrice !== null && $product->large_unit_id) {
                $updateData['large_retail_price'] = $newLargeSellingPrice;
            }

            // Update small unit wholesale price
            if ($wholesalePrice !== null) {
                $updateData['wholesale_price'] = $wholesalePrice;
            }

            // Update large unit wholesale price if provided
            if ($largeWholesalePrice !== null && $product->large_unit_id) {
                $updateData['large_wholesale_price'] = $largeWholesalePrice;
            }

            if (! empty($updateData)) {
                $product->update($updateData);
            }
        };

        // Always wrap in transaction to ensure atomicity and handle nested transactions correctly
        DB::transaction($execute);
    }

    /**
     * Post a sales invoice - creates stock movements
     */
    public function postSalesInvoice(SalesInvoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $execute = function () use ($invoice) {
            // Lock the invoice to prevent concurrent posting
            $invoice = SalesInvoice::with('items.product')->lockForUpdate()->findOrFail($invoice->id);

            // COGS Calculation using BC Math for precision
            $totalCOGS = '0';

            foreach ($invoice->items as $item) {
                $product = $item->product;

                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

                // Validate stock availability WITH LOCK inside transaction to prevent race conditions
                $currentStock = $this->getCurrentStock($invoice->warehouse_id, $product->id, true);

                if ($currentStock < $baseQuantity) {
                    throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
                }

                // CRITICAL: Calculate COGS using avg_cost at time of sale with BC Math
                $itemCOGS = bcmul((string) $product->avg_cost, (string) $baseQuantity, 4);
                $totalCOGS = bcadd($totalCOGS, $itemCOGS, 4);

                // Create negative stock movement (sale)
                $this->recordMovement(
                    $invoice->warehouse_id,
                    $product->id,
                    'sale',
                    -$baseQuantity, // Negative for sale
                    $product->avg_cost, // Store cost at time of sale
                    'sales_invoice',
                    $invoice->id
                );
            }

            // Store COGS on invoice
            $invoice->update(['cost_total' => $totalCOGS]);
        };

        // Always wrap in transaction to ensure atomicity
        DB::transaction($execute);
    }

    /**
     * Post a purchase invoice - creates stock movements and updates costs
     */
    public function postPurchaseInvoice(PurchaseInvoice $invoice): void
    {
        \Log::info('StockService::postPurchaseInvoice called', [
            'invoice_id' => $invoice->id,
            'invoice_status' => $invoice->status,
            'warehouse_id' => $invoice->warehouse_id,
            'items_count' => $invoice->items->count(),
            'transaction_level' => DB::transactionLevel(),
        ]);

        if (! $invoice->isDraft()) {
            throw new \Exception('الفاتورة ليست في حالة مسودة');
        }

        $invoice->load('items.product');

        $execute = function () use ($invoice) {
            \Log::info('Inside postPurchaseInvoice execute closure', [
                'invoice_id' => $invoice->id,
                'transaction_level' => DB::transactionLevel(),
            ]);

            foreach ($invoice->items as $item) {
                $product = $item->product;

                \Log::info('Processing invoice item', [
                    'item_id' => $item->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'quantity' => $item->quantity,
                    'unit_type' => $item->unit_type,
                    'warehouse_id' => $invoice->warehouse_id,
                ]);

                // Get stock before movement
                $stockBefore = $this->getCurrentStock($invoice->warehouse_id, $product->id);
                \Log::info('Stock before movement', [
                    'product_id' => $product->id,
                    'stock_before' => $stockBefore,
                ]);

                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

                \Log::info('Converted quantity', [
                    'original_quantity' => $item->quantity,
                    'base_quantity' => $baseQuantity,
                ]);

                // Calculate base unit cost (CRITICAL FIX for weighted average)
                // If purchasing in large units, divide cost by factor to get base unit cost
                $baseUnitCost = $item->unit_type === 'large' && $product->large_unit_id && $product->factor > 1
                    ? $item->unit_cost / $product->factor
                    : $item->unit_cost;

                \Log::info('Base unit cost calculation', [
                    'unit_type' => $item->unit_type,
                    'original_unit_cost' => $item->unit_cost,
                    'factor' => $product->factor,
                    'base_unit_cost' => $baseUnitCost,
                ]);

                // Create positive stock movement (purchase)
                $movement = $this->recordMovement(
                    $invoice->warehouse_id,
                    $product->id,
                    'purchase',
                    $baseQuantity, // Positive for purchase
                    $baseUnitCost, // Use base unit cost, not large unit cost
                    'purchase_invoice',
                    $invoice->id
                );

                \Log::info('Stock movement created', [
                    'movement_id' => $movement->id,
                    'product_id' => $product->id,
                    'quantity' => $baseQuantity,
                    'transaction_level' => DB::transactionLevel(),
                ]);

                // Check stock after movement (within transaction)
                $stockAfter = $this->getCurrentStock($invoice->warehouse_id, $product->id);
                \Log::info('Stock after movement', [
                    'product_id' => $product->id,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'expected_stock' => $stockBefore + $baseQuantity,
                ]);

                // Update product prices if any new prices are set
                if ($item->new_selling_price !== null || $item->new_large_selling_price !== null || $item->wholesale_price !== null || $item->large_wholesale_price !== null) {
                    $this->updateProductPrice(
                        $product,
                        $item->new_selling_price,
                        $item->unit_type,
                        $item->new_large_selling_price,
                        $item->wholesale_price,
                        $item->large_wholesale_price
                    );
                }

            }

            // Update average cost for all products in this invoice
            foreach ($invoice->items as $item) {
                $this->updateProductAvgCost($item->product_id);
            }

            \Log::info('StockService::postPurchaseInvoice execute completed', [
                'invoice_id' => $invoice->id,
                'transaction_level' => DB::transactionLevel(),
            ]);
        };

        // Always wrap in transaction to ensure atomicity and handle nested transactions correctly
        DB::transaction($execute);

        \Log::info('StockService::postPurchaseInvoice completed', [
            'invoice_id' => $invoice->id,
            'transaction_level' => DB::transactionLevel(),
        ]);
    }

    /**
     * Get the cost per unit for a returned item.
     * Tries to retrieve original sale cost from stock movement, falls back to current avg_cost.
     */
    protected function getCostForReturn(SalesReturn $return, string $productId): float
    {
        // If linked to an invoice, try to get the cost from the original sale movement
        if ($return->sales_invoice_id) {
            $saleMovement = StockMovement::where('reference_type', 'sales_invoice')
                ->where('reference_id', $return->sales_invoice_id)
                ->where('product_id', $productId)
                ->where('type', 'sale')
                ->first();

            if ($saleMovement) {
                return floatval($saleMovement->cost_at_time);
            }
        }

        // Fallback to current avg_cost
        $product = Product::find($productId);

        return floatval($product->avg_cost ?? 0);
    }

    /**
     * Post a sales return - creates stock movements (REVERSE of sale)
     */
    public function postSalesReturn(SalesReturn $return): void
    {
        if (! $return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        $return->load('items.product');

        // Validate return quantities if linked to an invoice
        if ($return->sales_invoice_id) {
            $invoice = SalesInvoice::with(['items.product', 'returns.items.product'])->find($return->sales_invoice_id);

            if ($invoice) {
                // Check if adding this return would exceed the invoice total
                $totalReturnedValue = $invoice->returns()->where('status', 'posted')->sum('total');
                $totalAfterThisReturn = floatval($totalReturnedValue) + floatval($return->total);

                if (bccomp((string) $totalAfterThisReturn, (string) $invoice->total, 4) > 0) {
                    throw new \Exception('لا يمكن تأكيد المرتجع: قيمة المرتجع تتجاوز قيمة الفاتورة المتبقية');
                }

                // Validate each return item against available quantities
                foreach ($return->items as $item) {
                    $availableQty = $invoice->getAvailableReturnQuantity($item->product_id, $item->unit_type);

                    if ($item->quantity > $availableQty) {
                        $productName = $item->product->name ?? 'Unknown';
                        throw new \Exception("الكمية المتاحة للإرجاع من المنتج '{$productName}' هي {$availableQty} فقط");
                    }
                }
            }
        }

        DB::transaction(function () use ($return) {
            $totalCOGSReversal = '0';

            foreach ($return->items as $item) {
                $product = $item->product;

                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

                // Get cost from original sale movement if available, otherwise use current avg_cost
                $costPerUnit = $this->getCostForReturn($return, $product->id);

                // Calculate COGS reversal (add back the cost) using BC Math
                $itemCOGSReversal = bcmul((string) $costPerUnit, (string) $baseQuantity, 4);
                $totalCOGSReversal = bcadd($totalCOGSReversal, $itemCOGSReversal, 4);

                // Create POSITIVE stock movement (sale_return)
                // REVERSE LOGIC: Sale removes stock (negative), return adds stock (positive)
                $this->recordMovement(
                    $return->warehouse_id,
                    $product->id,
                    'sale_return',
                    $baseQuantity, // POSITIVE for return
                    $costPerUnit,
                    'sales_return',
                    $return->id
                );
            }

            // Store cost_total on the SalesReturn record (Periodicity Principle)
            // The original invoice remains unchanged - preserving historical data
            $return->update(['cost_total' => $totalCOGSReversal]);
        });
    }

    /**
     * Post a purchase return - creates stock movements (REVERSE of purchase)
     */
    public function postPurchaseReturn(PurchaseReturn $return): void
    {
        if (! $return->isDraft()) {
            throw new \Exception('المرتجع ليس في حالة مسودة');
        }

        $return->load('items.product');

        // Validate return quantities if linked to an invoice
        if ($return->purchase_invoice_id) {
            $invoice = PurchaseInvoice::with(['items.product', 'returns.items.product'])->find($return->purchase_invoice_id);

            if ($invoice) {
                // Check if adding this return would exceed the invoice total
                $totalReturnedValue = $invoice->returns()->where('status', 'posted')->sum('total');
                $totalAfterThisReturn = floatval($totalReturnedValue) + floatval($return->total);

                if (bccomp((string) $totalAfterThisReturn, (string) $invoice->total, 4) > 0) {
                    throw new \Exception('لا يمكن تأكيد المرتجع: قيمة المرتجع تتجاوز قيمة الفاتورة المتبقية');
                }

                // Validate each return item against available quantities
                foreach ($return->items as $item) {
                    $availableQty = $invoice->getAvailableReturnQuantity($item->product_id, $item->unit_type);

                    if ($item->quantity > $availableQty) {
                        $productName = $item->product->name ?? 'Unknown';
                        throw new \Exception("الكمية المتاحة للإرجاع من المنتج '{$productName}' هي {$availableQty} فقط");
                    }
                }
            }
        }

        DB::transaction(function () use ($return) {
            foreach ($return->items as $item) {
                $product = $item->product;

                // Convert to base unit
                $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

                // Validate stock availability (we're removing stock)
                if (! $this->validateStockAvailability($return->warehouse_id, $product->id, $baseQuantity)) {
                    throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
                }

                // Calculate base unit cost (CRITICAL FIX for weighted average)
                // If returning large units, divide cost by factor to get base unit cost
                $baseUnitCost = $item->unit_type === 'large' && $product->large_unit_id && $product->factor > 1
                    ? $item->unit_cost / $product->factor
                    : $item->unit_cost;

                // Create NEGATIVE stock movement (purchase_return)
                // REVERSE LOGIC: Purchase adds stock (positive), return removes stock (negative)
                $this->recordMovement(
                    $return->warehouse_id,
                    $product->id,
                    'purchase_return',
                    -$baseQuantity, // NEGATIVE for return
                    $baseUnitCost, // Use base unit cost, not large unit cost
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
        if (! $adjustment->isDraft()) {
            throw new \Exception('التسوية ليست في حالة مسودة');
        }

        DB::transaction(function () use ($adjustment) {
            // Eager load product to prevent lazy loading
            $adjustment->load('product');
            $product = $adjustment->product;

            // BLIND-03 FIX: Simplified sign logic with type-based defaults
            // - Types 'damage' and 'gift' are ALWAYS subtractions (stock out)
            // - Type 'opening' is ALWAYS addition (stock in)
            // - Type 'other' uses the quantity sign to determine direction
            // This provides semantic clarity while still allowing flexibility

            $quantity = (float) $adjustment->quantity;
            $subtractionTypes = ['damage', 'gift'];
            $additionTypes = ['opening'];

            // Determine if this is a subtraction based on type or sign
            if (in_array($adjustment->type, $subtractionTypes)) {
                // Subtraction types: ensure quantity is negative (normalize)
                $movementType = 'adjustment_out';
                $quantity = -abs($quantity);

                // Validate stock availability for subtraction
                if (! $this->validateStockAvailability($adjustment->warehouse_id, $product->id, abs($quantity))) {
                    throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
                }
            } elseif (in_array($adjustment->type, $additionTypes)) {
                // Addition types: ensure quantity is positive (normalize)
                $movementType = 'adjustment_in';
                $quantity = abs($quantity);
            } else {
                // 'other' type: use quantity sign directly
                if ($quantity < 0) {
                    $movementType = 'adjustment_out';

                    // Validate stock availability for subtraction
                    if (! $this->validateStockAvailability($adjustment->warehouse_id, $product->id, abs($quantity))) {
                        throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
                    }
                } else {
                    $movementType = 'adjustment_in';
                }
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
     *
     * BLIND-02 FIX: Added stock validation before transfer
     */
    public function postWarehouseTransfer(WarehouseTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            // Eager load items with products to prevent lazy loading
            $transfer->load('items.product');

            // BLIND-02 FIX: Validate stock availability BEFORE creating any movements
            foreach ($transfer->items as $item) {
                $product = $item->product;

                // Get current stock with lock to prevent race conditions
                $currentStock = $this->getCurrentStock($transfer->from_warehouse_id, $product->id, true);

                if ($currentStock < $item->quantity) {
                    throw new \Exception("المخزون غير كافٍ للنقل: {$product->name}. المتاح: {$currentStock}, المطلوب: {$item->quantity}");
                }
            }

            // Now create the movements after validation passes
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
