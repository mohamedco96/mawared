# 🌱 Database Seeders

## Available Seeders

### 🏠 **HomeGoodsSeeder** - Home Appliances Business Demo
**File**: `HomeGoodsSeeder.php`

A comprehensive seeder that creates a realistic home appliances business scenario with proper financial integrity.

**What it creates:**
- 10 home appliance products (blenders, air fryers, cookware, etc.)
- 2 suppliers with purchase invoices (cash & credit)
- 2 customers with sales invoices (cash & partial credit)
- 1 sales return (defective product)
- 1 expense (store rent)
- Initial capital injection (1,000,000 EGP)

**Expected Results:**
- **Stock**: 61 items across 3 product types
- **Treasury Balance**: 1,004,800 EGP
- **Gross Profit**: 38,000 EGP (from 2 sales)
- **Supplier Debts**: -225,000 EGP (we owe)
- **Customer Debts**: +60,000 EGP (they owe us)

**Usage:**

```bash
# Fresh database (recommended for demo)
php artisan migrate:fresh --seed --seeder=HomeGoodsSeeder

# Add to existing database
php artisan db:seed --class=HomeGoodsSeeder
```

**Features:**
✅ Properly posts invoices via Services (StockService & TreasuryService)
✅ Atomic transactions (all-or-nothing)
✅ Beautiful console output with verification
✅ Self-checking (compares expected vs actual)
✅ Idempotent products (reuses if exist)

**Documentation:**
- `HOME_GOODS_SEEDER_GUIDE.md` - Detailed implementation guide
- `HOME_GOODS_SEEDER_SUCCESS.md` - Success metrics & verification

---

## The Golden Rule for Invoice Seeding

**❌ WRONG** (Does NOT update stock/treasury):
```php
PurchaseInvoice::create(['status' => 'posted', ...]);
```

**✅ CORRECT** (Properly posts via services):
```php
$invoice = PurchaseInvoice::create(['status' => 'draft', ...]);
$invoice->items()->create([...]);

DB::transaction(function() use ($invoice) {
    app(StockService::class)->postPurchaseInvoice($invoice);
    app(TreasuryService::class)->postPurchaseInvoice($invoice);
    $invoice->status = 'posted';
    $invoice->saveQuietly();
    $invoice->partner->recalculateBalance();
});
```

**Why?**
- Model events don't auto-post invoices
- Services validate business rules (stock availability, treasury balance)
- Services create stock movements & treasury transactions
- Transactions ensure atomicity

---

## Other Seeders

### System Seeders (Required First)
1. **GeneralSettingSeeder** - System configuration
2. **AdminUserSeeder** - Admin user
3. **UnitSeeder** - Product units (قطعة, كرتونة, etc.)
4. **WarehouseSeeder** - Warehouses
5. **TreasurySeeder** - Treasury accounts

### Sample Data Seeders
6. **PartnerSeeder** - Sample customers/suppliers
7. **ProductSeeder** - Sample products (kitchenware)
8. **PurchaseInvoiceSeeder** - Sample purchase invoices (DRAFT only)
9. **SalesInvoiceSeeder** - Sample sales invoices (DRAFT only)
10. **ExpenseSeeder** - Sample expenses
11. **RevenueSeeder** - Sample revenues

### Demo Seeders
12. **HomeGoodsSeeder** ⭐ - Complete home appliances business (POSTED)

---

## Running All Seeders

```bash
# Run all seeders defined in DatabaseSeeder
php artisan db:seed

# Fresh migration + seed
php artisan migrate:fresh --seed

# Specific seeder
php artisan db:seed --class=HomeGoodsSeeder
```

---

## Creating New Seeders

### Template for Invoice Seeder with Proper Posting

```php
<?php

namespace Database\Seeders;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Services\StockService;
use App\Services\TreasuryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MyInvoiceSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create invoice as draft
        $invoice = PurchaseInvoice::create([
            'status' => 'draft',
            'warehouse_id' => $warehouseId,
            'partner_id' => $supplierId,
            // ... other fields
        ]);

        // 2. Add items
        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $invoice->id,
            'product_id' => $productId,
            'quantity' => 10,
            'unit_cost' => 100,
            'total' => 1000,
        ]);

        // 3. Update invoice totals
        $invoice->update([
            'subtotal' => 1000,
            'total' => 1000,
            'paid_amount' => 500,
            'remaining_amount' => 500,
        ]);

        // 4. Post via services
        DB::transaction(function () use ($invoice) {
            app(StockService::class)->postPurchaseInvoice($invoice);
            app(TreasuryService::class)->postPurchaseInvoice($invoice);
            $invoice->status = 'posted';
            $invoice->saveQuietly();
            $invoice->partner->recalculateBalance();
        });
    }
}
```

---

## Best Practices

### ✅ DO
- Use Services to post invoices
- Wrap posting in DB::transaction()
- Use `saveQuietly()` when updating status
- Call `recalculateBalance()` after posting
- Verify expected vs actual results
- Add beautiful console output
- Make seeders idempotent when possible

### ❌ DON'T
- Create invoices with `status='posted'` directly
- Bypass Services layer
- Forget to recalculate partner balances
- Create stock movements manually
- Create treasury transactions manually
- Use model events for financial operations

---

## Seeder Order (DatabaseSeeder.php)

```php
public function run(): void
{
    $this->call([
        // 1. System Setup (Required)
        GeneralSettingSeeder::class,
        AdminUserSeeder::class,

        // 2. Base Data (Required)
        UnitSeeder::class,
        WarehouseSeeder::class,
        TreasurySeeder::class,

        // 3. Master Data
        PartnerSeeder::class,
        ProductSeeder::class,

        // 4. Transactions (Draft only - safe)
        SalesInvoiceSeeder::class,
        PurchaseInvoiceSeeder::class,

        // 5. Demo Data (Posted - realistic)
        HomeGoodsSeeder::class, // ⭐ New!

        // 6. Other
        ExpenseSeeder::class,
        RevenueSeeder::class,
    ]);
}
```

---

## Troubleshooting

### "Column not found"
**Solution**: Check model fillable fields and migration

### "Integrity constraint violation"
**Solution**: Ensure foreign keys exist (warehouse, treasury, partner, product)

### "Cannot update a posted invoice"
**Solution**: Create as draft first, post via services

### "المخزون غير كافٍ"
**Solution**: Create purchase before sale, or reduce sale quantity

### "الرصيد المتاح غير كافٍ في الخزينة"
**Solution**: Inject capital first, or reduce payment amount

---

## Useful Commands

```bash
# Rollback last migration
php artisan migrate:rollback

# Fresh migration (drops all tables)
php artisan migrate:fresh

# Fresh + seed
php artisan migrate:fresh --seed

# Seed specific class
php artisan db:seed --class=HomeGoodsSeeder

# Create new seeder
php artisan make:seeder MySeeder
```

---

## Resources

- **Seeder Documentation**: `HOME_GOODS_SEEDER_GUIDE.md`
- **Success Metrics**: `HOME_GOODS_SEEDER_SUCCESS.md`
- **Laravel Docs**: https://laravel.com/docs/seeding
- **Project Rules**: `../PROJECT_RULES.md`

---

**Last Updated**: 2025-12-28
**Maintained by**: Development Team
