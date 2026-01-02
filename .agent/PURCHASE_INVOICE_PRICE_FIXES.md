# Purchase Invoice Price Updates - Implementation Summary

## Issues Fixed

### 1. ✅ Added wholesale_price and large_wholesale_price fields
- Added new optional fields to purchase invoice items with the same logic as `new_selling_price`
- These fields allow updating product wholesale prices when posting purchase invoices

### 2. ✅ Fixed new_large_selling_price not updating product.large_retail_price
- The `StockService::updateProductPrice()` method was not receiving the `new_large_selling_price` parameter
- Now properly updates `product.large_retail_price` when `new_large_selling_price` is set

## Changes Made

### Database Migration
**File**: `database/migrations/2026_01_01_210800_add_wholesale_prices_to_purchase_invoice_items.php`
- Added `wholesale_price` column (decimal 15,4, nullable)
- Added `large_wholesale_price` column (decimal 15,4, nullable)

### Model Updates
**File**: `app/Models/PurchaseInvoiceItem.php`
- Added `wholesale_price` and `large_wholesale_price` to fillable array
- Added casts for both fields as `decimal:4`

### Service Layer
**File**: `app/Services/StockService.php`

#### Updated `updateProductPrice()` method signature:
```php
public function updateProductPrice(
    Product $product, 
    ?string $newSellingPrice, 
    string $unitType, 
    ?string $newLargeSellingPrice = null,
    ?string $wholesalePrice = null,
    ?string $largeWholesalePrice = null
): void
```

#### Now updates 4 price fields when provided:
1. `product.retail_price` ← from `new_selling_price`
2. `product.large_retail_price` ← from `new_large_selling_price` ✅ **FIXED**
3. `product.wholesale_price` ← from `wholesale_price` ✅ **NEW**
4. `product.large_wholesale_price` ← from `large_wholesale_price` ✅ **NEW**

#### Updated `postPurchaseInvoice()` method:
- Now checks for and passes all 4 price fields to `updateProductPrice()`
- Fixed the bug where `new_large_selling_price` was not being passed

### Filament Resource
**File**: `app/Filament/Resources/PurchaseInvoiceResource.php`

#### Added form fields:
1. **wholesale_price** (سعر الجملة الجديد - صغير)
   - Optional numeric field
   - Auto-calculates `large_wholesale_price` based on product factor
   - Live update on blur
   - Same UX as `new_selling_price`

2. **large_wholesale_price** (سعر الجملة الجديد - كبير)
   - Optional numeric field  
   - Auto-calculated from `wholesale_price × factor`
   - Can be manually overridden
   - Only visible for products with large units
   - Same UX as `new_large_selling_price`

#### Updated post action:
- Now passes all 4 price fields when calling `updateProductPrice()`
- Ensures wholesale prices are updated when invoice is posted

## Behavior

### Auto-calculation Logic
When entering a small unit price, the large unit price is automatically calculated:
- `new_large_selling_price = new_selling_price × factor`
- `large_wholesale_price = wholesale_price × factor`

Both auto-calculated values can be manually overridden.

### Update Trigger
Prices are updated in two places:
1. **During posting** (when invoice status changes from draft → posted)
2. **In StockService::postPurchaseInvoice()** - within the same transaction as stock movements

### All fields are optional
- Users can update any combination of the 4 price fields
- If a field is null/empty, the corresponding product price remains unchanged

## Testing Recommendations

1. **Create purchase invoice** with products that have both small and large units
2. **Test new_selling_price** → verify `product.retail_price` updates
3. **Test new_large_selling_price** → verify `product.large_retail_price` updates ✅
4. **Test wholesale_price** → verify `product.wholesale_price` updates ✅
5. **Test large_wholesale_price** → verify `product.large_wholesale_price` updates ✅
6. **Test auto-calculation** → enter small unit price, verify large unit price calculates correctly
7. **Test manual override** → enter small unit price, then manually change large unit price
8. **Test posting invoice** → verify all prices update in database when invoice is posted
