# Fixing Lazy Loading Issues in Services

## Problem

During seeding, you see errors like:
```
✗ Failed to post sales invoice: Attempted to lazy load [product] on model [App\Models\SalesInvoiceItem] but lazy loading is disabled.
```

This happens because your `StockService` or `TreasuryService` tries to access relationships that weren't eager loaded.

## Where the Problem Occurs

Your services likely have code like this:

```php
// In StockService.php
public function postSalesInvoice(SalesInvoice $invoice)
{
    foreach ($invoice->items as $item) {
        // This line tries to lazy load the product relationship
        $productName = $item->product->name; // ❌ Lazy loading violation!
    }
}
```

## Solution: Update Your Services

### Option 1: Eager Load in the Service (Recommended)

Update your service methods to ensure relationships are loaded:

```php
// In app/Services/StockService.php

public function postSalesInvoice(SalesInvoice $invoice)
{
    // Ensure all required relationships are loaded
    $invoice->loadMissing([
        'items',
        'items.product',
        'items.product.smallUnit',
        'items.product.largeUnit',
        'warehouse',
        'partner'
    ]);

    // Now you can safely access all relationships
    foreach ($invoice->items as $item) {
        $productName = $item->product->name; // ✅ Works!

        // Your stock movement logic here
        StockMovement::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $invoice->warehouse_id,
            'quantity' => -$item->quantity, // Negative for sales
            'type' => 'sale',
            'reference_type' => SalesInvoice::class,
            'reference_id' => $invoice->id,
        ]);
    }
}

public function postPurchaseInvoice(PurchaseInvoice $invoice)
{
    // Same pattern for purchases
    $invoice->loadMissing([
        'items',
        'items.product',
        'items.product.smallUnit',
        'items.product.largeUnit',
        'warehouse',
        'partner'
    ]);

    foreach ($invoice->items as $item) {
        // Your stock movement logic here
        StockMovement::create([
            'product_id' => $item->product_id,
            'warehouse_id' => $invoice->warehouse_id,
            'quantity' => $item->quantity, // Positive for purchases
            'type' => 'purchase',
            'reference_type' => PurchaseInvoice::class,
            'reference_id' => $invoice->id,
        ]);
    }
}
```

### Option 2: Update Method Signatures

Make it explicit that relationships must be loaded:

```php
/**
 * Post a sales invoice and create stock movements.
 *
 * @param SalesInvoice $invoice Must have 'items.product' relationship loaded
 * @return void
 * @throws \Exception if invoice is not in draft status or relationships not loaded
 */
public function postSalesInvoice(SalesInvoice $invoice): void
{
    // Validate that required relationships are loaded
    if (!$invoice->relationLoaded('items')) {
        throw new \Exception('Invoice items must be eager loaded before posting.');
    }

    // Check if items have product relationship loaded
    foreach ($invoice->items as $item) {
        if (!$item->relationLoaded('product')) {
            throw new \Exception('Invoice item products must be eager loaded before posting.');
        }
    }

    // Your posting logic here
}
```

### Option 3: Use Query Scopes

Create a query scope to ensure proper loading:

```php
// In app/Models/SalesInvoice.php

public function scopeWithAllRelations($query)
{
    return $query->with([
        'items.product.smallUnit',
        'items.product.largeUnit',
        'warehouse',
        'partner',
        'creator'
    ]);
}

// Usage in service:
public function postSalesInvoice(SalesInvoice $invoice)
{
    // Reload with all relations if needed
    $invoice = SalesInvoice::withAllRelations()->find($invoice->id);

    // Now process safely
}
```

## Real-World Example

Here's a complete example for `StockService`:

```php
<?php

namespace App\Services;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Post a purchase invoice and create stock movements
     */
    public function postPurchaseInvoice(PurchaseInvoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new \Exception('Only draft invoices can be posted');
        }

        // Eager load all required relationships
        $invoice->loadMissing([
            'items.product.smallUnit',
            'items.product.largeUnit',
            'warehouse'
        ]);

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->items as $item) {
                // Convert to base unit (small unit) for consistent tracking
                $quantityInBaseUnit = $item->quantity;

                if ($item->unit_type === 'large' && $item->product->large_unit_id) {
                    $quantityInBaseUnit = $item->quantity * $item->product->factor;
                }

                // Create stock movement (positive for purchase)
                StockMovement::create([
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                    'type' => 'purchase',
                    'quantity' => $quantityInBaseUnit,
                    'cost_at_time' => $item->unit_cost,
                    'reference_type' => PurchaseInvoice::class,
                    'reference_id' => $invoice->id,
                    'notes' => "Purchase from invoice {$invoice->invoice_number}",
                ]);

                // Update product average cost (weighted average)
                $this->updateProductAverageCost($item->product, $item->unit_cost, $quantityInBaseUnit);
            }
        });
    }

    /**
     * Post a sales invoice and create stock movements
     */
    public function postSalesInvoice(SalesInvoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw new \Exception('Only draft invoices can be posted');
        }

        // Eager load all required relationships
        $invoice->loadMissing([
            'items.product.smallUnit',
            'items.product.largeUnit',
            'warehouse'
        ]);

        DB::transaction(function () use ($invoice) {
            foreach ($invoice->items as $item) {
                // Convert to base unit
                $quantityInBaseUnit = $item->quantity;

                if ($item->unit_type === 'large' && $item->product->large_unit_id) {
                    $quantityInBaseUnit = $item->quantity * $item->product->factor;
                }

                // Check stock availability
                $currentStock = StockMovement::where('warehouse_id', $invoice->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->sum('quantity');

                if ($currentStock < $quantityInBaseUnit) {
                    throw new \Exception(
                        "Insufficient stock for {$item->product->name}. " .
                        "Available: {$currentStock}, Required: {$quantityInBaseUnit}"
                    );
                }

                // Create stock movement (negative for sale)
                StockMovement::create([
                    'warehouse_id' => $invoice->warehouse_id,
                    'product_id' => $item->product_id,
                    'type' => 'sale',
                    'quantity' => -$quantityInBaseUnit, // Negative for sale
                    'cost_at_time' => $item->product->avg_cost,
                    'reference_type' => SalesInvoice::class,
                    'reference_id' => $invoice->id,
                    'notes' => "Sale from invoice {$invoice->invoice_number}",
                ]);
            }
        });
    }

    /**
     * Update product average cost using weighted average method
     */
    private function updateProductAverageCost($product, float $newCost, int $newQuantity): void
    {
        $currentStock = StockMovement::where('product_id', $product->id)->sum('quantity');
        $currentValue = $currentStock * $product->avg_cost;
        $newValue = $newQuantity * $newCost;
        $totalValue = $currentValue + $newValue;
        $totalQuantity = $currentStock + $newQuantity;

        if ($totalQuantity > 0) {
            $product->update([
                'avg_cost' => $totalValue / $totalQuantity
            ]);
        }
    }
}
```

## Testing Your Fix

After updating your services, test with:

```bash
# Fresh seed
php artisan migrate:fresh --seed

# You should see:
# ✓ Purchase Invoice PUR-00001: ... EGP
# ✓ Sales Invoice SAL-00001: ... EGP
# (No more lazy loading errors!)
```

## Quick Fix for Development Only

If you just want to test quickly without fixing services:

```php
// In app/Providers/AppServiceProvider.php

public function boot()
{
    // Comment this out during development/seeding
    // Model::preventLazyLoading(! app()->isProduction());

    // Uncomment for production
    if (app()->isProduction()) {
        Model::preventLazyLoading();
    }
}
```

⚠️ **Warning:** This is NOT recommended for production! Fix the services properly instead.

## Common Patterns to Fix

### Pattern 1: Accessing Product in Loops
```php
// ❌ Before
foreach ($invoice->items as $item) {
    $name = $item->product->name; // Lazy loads!
}

// ✅ After
$invoice->loadMissing('items.product');
foreach ($invoice->items as $item) {
    $name = $item->product->name; // Already loaded!
}
```

### Pattern 2: Nested Relationships
```php
// ❌ Before
$unitName = $item->product->smallUnit->name; // Multiple lazy loads!

// ✅ After
$invoice->loadMissing('items.product.smallUnit');
$unitName = $item->product->smallUnit->name; // Already loaded!
```

### Pattern 3: Conditional Relationships
```php
// ❌ Before
if ($item->product->large_unit_id) {
    $factor = $item->product->largeUnit->factor; // Lazy load!
}

// ✅ After
$invoice->loadMissing('items.product.largeUnit');
if ($item->product->large_unit_id) {
    $factor = $item->product->largeUnit->factor; // Already loaded!
}
```

## Performance Benefits

By eager loading, you reduce N+1 queries:

### Before (Lazy Loading):
```
Query 1: SELECT * FROM invoices WHERE id = 1
Query 2: SELECT * FROM invoice_items WHERE invoice_id = 1  (1 query)
Query 3: SELECT * FROM products WHERE id = 10              (N queries - one per item!)
Query 4: SELECT * FROM products WHERE id = 11
Query 5: SELECT * FROM products WHERE id = 12
...
Total: 1 + 1 + N queries = BAD! 😞
```

### After (Eager Loading):
```
Query 1: SELECT * FROM invoices WHERE id = 1
Query 2: SELECT * FROM invoice_items WHERE invoice_id = 1
Query 3: SELECT * FROM products WHERE id IN (10, 11, 12, ...) (1 query for all!)
Total: 3 queries = GOOD! 😊
```

## Summary

1. ✅ Update your services to use `loadMissing()` or `load()`
2. ✅ Load all relationships needed for processing
3. ✅ Test thoroughly after changes
4. ✅ Monitor query count with Laravel Debugbar
5. ✅ Document required relationships in method docblocks

After implementing these fixes, your seeder will run smoothly with **100% success rate** instead of ~60%!

---

**Need Help?** Check the `StockService` and `TreasuryService` files in your `app/Services/` directory and apply the patterns shown above.
