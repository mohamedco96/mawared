# Data Migration Documentation

## Overview
This document describes the data migration from the legacy system to the new Mawared ERP system.

---

## Migration Order

Run seeders in this exact order:

```bash
php artisan db:seed --class=WarehouseImportSeeder
php artisan db:seed --class=CategoryImportSeeder
php artisan db:seed --class=ProductsImportSeeder
php artisan db:seed --class=PartnersImportSeeder
php artisan db:seed --class=PurchaseInvoicesImportSeeder
php artisan db:seed --class=SalesInvoicesImportSeeder
php artisan db:seed --class=PurchaseReturnsImportSeeder
php artisan db:seed --class=SalesReturnsImportSeeder
php artisan db:seed --class=ExpensesImportSeeder
```

---

## What Gets Migrated

### 1. Warehouses (WarehouseImportSeeder)
- **Source:** `stor.csv`
- **Creates:** Warehouse records
- **Example:** "رئيسى" (Main warehouse)

### 2. Product Categories (CategoryImportSeeder)
- **Source:** `kind.csv`
- **Creates:** Product category records
- **Example:** "عام" (General)

### 3. Products (ProductsImportSeeder)
- **Source:** `main.csv`
- **Creates:**
  - Product records with pricing
  - Auto-creates units (e.g., قطعة, طقم)
- **Total:** ~121 products

### 4. Partners (PartnersImportSeeder)
- **Source:** `tel.csv`
- **Creates:**
  - Customers (type: 'customer')
  - Suppliers (type: 'supplier')
  - Unknown contacts (type: 'unknown')
- **Total:** ~54 partners (34 customers, 17 suppliers, 3 unknown)

### 5. Purchase Invoices (PurchaseInvoicesImportSeeder)
- **Source:**
  - Headers: `purchase_invoice_headers.csv`
  - Items: `purchase_invoice_items.csv`
- **Creates:**
  - Purchase invoice records (status: 'posted')
  - Purchase invoice items
- **Total:** ~40 invoices with ~150 items
- **Note:** All invoices are credit (no cash payments)

### 6. Sales Invoices (SalesInvoicesImportSeeder)
- **Source:**
  - Headers: `sales_invoice_headers.csv`
  - Items: `sales_invoice_items.csv`
- **Creates:**
  - Sales invoice records (status: 'posted')
  - Sales invoice items
- **Total:** ~90 invoices with ~400 items
- **Note:** All invoices are credit (no cash payments)

### 7. Purchase Returns (PurchaseReturnsImportSeeder)
- **Source:**
  - Headers: `purchase_return_headers.csv`
  - Items: `purchase_return_items.csv`
- **Creates:**
  - Purchase return records (status: 'posted')
  - Purchase return items
  - Return numbers prefixed with 'PR-'
- **Total:** ~5 returns with ~17 items

### 8. Sales Returns (SalesReturnsImportSeeder)
- **Source:**
  - Headers: `sales_return_headers.csv`
  - Items: `sales_return_items.csv`
- **Creates:**
  - Sales return records (status: 'posted')
  - Sales return items
  - Return numbers prefixed with 'SR-'
- **Total:** ~1 return with ~1 item

### 9. Expenses (ExpensesImportSeeder)
- **Source:** `expenses.csv`
- **Creates:**
  - Expense records
  - Auto-creates treasury "رئيسى" if needed
- **Total:** ~50 expenses
- **Categories:** بنزين, مصاريف سيارة, اخرى, etc.

---

## Important Notes

### ⚠️ What is NOT Migrated

The migration only imports **data records**. It does NOT create:

- ❌ Stock movements
- ❌ Treasury transactions
- ❌ Partner balance calculations
- ❌ Product cost updates

All records are created with `status='posted'` but without triggering business logic.

### 🔄 After Migration

To activate full functionality, you need to manually:

1. **Post Invoices/Returns** through the UI to create stock movements and treasury transactions
2. **Recalculate Partner Balances** if needed
3. **Update Product Costs** if needed

OR you can use the system's posting functionality to retroactively process all imported records.

### 📋 Idempotency

All seeders are **idempotent** - you can run them multiple times safely:
- Purchase/Sales Invoices: Checks by `invoice_number`
- Returns: Checks by `return_number` (with PR-/SR- prefix)
- Expenses: Checks by combination of date, amount, title, description
- Products: Checks by `name`
- Partners: Checks by `legacy_id`
- Warehouses/Categories: Checks by `name`

### 🎯 Data Mapping

#### Invoice/Return Numbers
- Purchase Invoices: Uses legacy `no_f` as-is
- Sales Invoices: Uses legacy `no_f` as-is
- Purchase Returns: Prefixed with **'PR-'** + legacy `no_s`
- Sales Returns: Prefixed with **'SR-'** + legacy `no_s`

#### Dates
- Format: MM/DD/YY in CSV → Converted to Y-m-d in database
- Invalid dates default to current date

#### Pricing
- All prices are in Egyptian Pounds (EGP)
- Decimal precision: 4 places

#### Encoding
- All Arabic text properly handled with UTF-8 encoding

---

## Verification Queries

After migration, verify data:

```bash
# Check counts
php artisan tinker --execute="
echo 'Warehouses: ' . \App\Models\Warehouse::count() . PHP_EOL;
echo 'Categories: ' . \App\Models\ProductCategory::count() . PHP_EOL;
echo 'Products: ' . \App\Models\Product::count() . PHP_EOL;
echo 'Partners: ' . \App\Models\Partner::count() . PHP_EOL;
echo 'Purchase Invoices: ' . \App\Models\PurchaseInvoice::count() . PHP_EOL;
echo 'Sales Invoices: ' . \App\Models\SalesInvoice::count() . PHP_EOL;
echo 'Purchase Returns: ' . \App\Models\PurchaseReturn::count() . PHP_EOL;
echo 'Sales Returns: ' . \App\Models\SalesReturn::count() . PHP_EOL;
echo 'Expenses: ' . \App\Models\Expense::count() . PHP_EOL;
"
```

---

## Troubleshooting

### Missing Products
If items are skipped due to missing products:
- Check product name spelling in CSVs
- Names must match exactly (case-sensitive)
- Arabic text encoding issues may cause mismatches

### Missing Partners
If invoices use "general" customer/supplier:
- Partner name from CSV not found in partners table
- Check `tel.csv` for missing entries
- System uses fallback: "عميل نقدي" for customers, "مورد عام" for suppliers

### Warehouse Required
All transaction seeders require at least one warehouse:
- Run `WarehouseImportSeeder` first
- Or manually create a warehouse before running transaction seeders

---

## CSV File Locations

All CSV files should be in: `database/seeders/data/`

```
database/seeders/data/
├── stor.csv                           # Warehouses
├── kind.csv                           # Categories
├── main.csv                           # Products
├── tel.csv                            # Partners
├── purchase_invoice_headers.csv       # Purchase invoices
├── purchase_invoice_items.csv         # Purchase items
├── sales_invoice_headers.csv          # Sales invoices
├── sales_invoice_items.csv            # Sales items
├── purchase_return_headers.csv        # Purchase returns
├── purchase_return_items.csv          # Purchase return items
├── sales_return_headers.csv           # Sales returns
├── sales_return_items.csv             # Sales return items
└── expenses.csv                       # Expenses
```

---

**Last Updated:** January 2026
