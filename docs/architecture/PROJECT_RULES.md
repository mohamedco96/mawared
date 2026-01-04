# Mawared ERP - Project Rules & Architecture Guidelines

> **Version:** 1.0  
> **Last Updated:** December 2025  
> **Purpose:** This document defines the NON-NEGOTIABLE architectural principles, database standards, and coding guidelines for all Mawared ERP development.

---

## 1. Core Architecture (NON-NEGOTIABLE)

### A. The Single-Ledger Philosophy

**The Core Principle:** Centralized ledgers are the ONLY source of truth for reporting and calculations.

#### Inventory Truth
* **`stock_movements` table** is the ONLY source of truth for stock levels.
* ❌ **FORBIDDEN:** Never calculate stock by summing invoices or querying invoice items.
* ✅ **REQUIRED:** Always query `stock_movements` table for current stock, stock history, and reports.
* **Benefit:** Enables drill-down features (clicking a movement opens the original invoice).

#### Financial Truth
* **`treasury_transactions` table** is the ONLY source of truth for cash flow.
* ❌ **FORBIDDEN:** Never calculate balances from invoices or payments directly.
* ✅ **REQUIRED:** Always query `treasury_transactions` for financial reports, partner balances, and treasury balances.
* **Benefit:** Single source of truth for all financial operations.

#### Service Layer Architecture
* **Business Logic Location:** All business logic MUST reside in `StockService` and `TreasuryService`.
* ❌ **FORBIDDEN:** Do NOT use Model Observers (`creating`, `created`, `updating`, `updated`) for core business logic.
    * Examples of FORBIDDEN logic in Observers:
        * Balance updates (`Partner::current_balance`)
        * Stock deductions/additions
        * Treasury transaction creation
        * Product cost/price updates
* ✅ **REQUIRED:** Use explicit service method calls from Filament Resources or Controllers.
    * Example: `StockService::postSalesInvoice($invoice)`
    * Example: `TreasuryService::recordTransaction(...)`
* **Atomic Operations:** All service methods that perform multi-step operations MUST be wrapped in `DB::transaction()`.
    * Example: If stock deduction fails, partner balance update must rollback.

---

### B. Dual-Unit Inventory System

**The Core Principle:** Products can be sold in two units (Small/Piece and Large/Carton) without a separate units table.

#### Storage Structure
* ❌ **FORBIDDEN:** Do NOT create a separate `product_units` table.
* ✅ **REQUIRED:** Store unit information directly in the `products` table:
    * `small_unit_id` (Base Unit - REQUIRED)
    * `large_unit_id` (Optional - nullable)
    * `factor` (Integer - Conversion rate from small to large, default: 1)

#### Calculation Rules
* **Base Unit:** All database storage and calculations MUST be done in the **Base Unit** (Small Unit).
* **Conversion:** When a user selects "Large Unit" in the UI:
    * Display quantity in Large Units
    * Store quantity in Base Units: `quantity_in_base = quantity_in_large * product.factor`
* **Example:**
    * Product: "Coca Cola"
    * Small Unit: "Piece" (Base)
    * Large Unit: "Carton" (Factor: 12)
    * User sells: 2 Cartons
    * Database stores: `quantity = -24` (negative for sale, 2 * 12 = 24 pieces)

#### Pricing
* Products have separate prices for Small and Large units:
    * `retail_price`, `wholesale_price` (for Small Unit)
    * `large_retail_price`, `large_wholesale_price` (for Large Unit, nullable)

---

## 2. Database Standards

### A. Primary Keys & Foreign Keys

#### ULID Strategy (Offline-Ready)
* **Primary Keys:** MUST use **ULIDs** (Universally Unique Lexicographically Sortable Identifiers).
    * Migration: `$table->ulid('id')->primary()`
    * Model: Use `HasUlids` trait
    * **Reason:** Enables future offline/sync capabilities without conflicts.
* **Foreign Keys:** MUST use `$table->foreignUlid('user_id')` instead of `$table->foreignId()`.
* **Business Keys:** Commercial documents MUST have a human-readable identifier:
    * `invoice_number` (e.g., `INV-2025-001`)
    * `transfer_number` (e.g., `TRF-2025-001`)
    * Generated sequentially, unique, indexed.

---

### B. Data Precision & Types

#### Monetary Values
* **Type:** MUST be `decimal(18, 4)`.
* **Applies To:**
    * Prices (`retail_price`, `wholesale_price`)
    * Costs (`avg_cost`, `unit_cost`)
    * Totals (`subtotal`, `total`, `discount`)
    * Balances (`current_balance`, `amount`)
* ❌ **FORBIDDEN:** Never use `float` or `double` for money.
* **Reason:** Prevents rounding errors and ensures financial accuracy.

#### Quantities
* **Type:** MUST be `integer`.
* **Applies To:**
    * Stock quantities
    * Invoice item quantities
    * All quantity fields in `stock_movements`
* ❌ **FORBIDDEN:** No fractional stock allowed (e.g., 1.5 pieces).
* **Reason:** We deal in whole Base Units only.

---

### C. Data Safety & Integrity

#### Soft Deletes
* **REQUIRED** on all core models:
    * `Product`
    * `Partner`
    * `SalesInvoice`, `PurchaseInvoice`
    * `StockMovement`
    * `TreasuryTransaction`
    * `StockAdjustment`
    * `WarehouseTransfer`
* **Migration:** `$table->softDeletes()`
* **Model:** `use SoftDeletes;`
* **Reason:** Preserves historical data and enables audit trails.

#### Database Transactions
* **REQUIRED:** All Service Layer methods that perform multi-step operations MUST be wrapped in `DB::transaction()`.
* **Example:**
    ```php
    public function postSalesInvoice(SalesInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            // 1. Create stock movements
            // 2. Create treasury transactions
            // 3. Update partner balance
            // If any step fails, all rollback
        });
    }
    ```
* **Reason:** Ensures data consistency and prevents partial updates.

#### Polymorphism
* **MorphMap:** MUST register a `MorphMap` in `AppServiceProvider::boot()`.
* **Example:**
    ```php
    Relation::enforceMorphMap([
        'sales_invoice' => SalesInvoice::class,
        'purchase_invoice' => PurchaseInvoice::class,
        'warehouse_transfer' => WarehouseTransfer::class,
        'stock_adjustment' => StockAdjustment::class,
        'expense' => Expense::class,
        'revenue' => Revenue::class,
        'treasury_transaction' => TreasuryTransaction::class,
    ]);
    ```
* ❌ **FORBIDDEN:** Do NOT store full class names (e.g., `App\Models\SalesInvoice`) in the database.
* ✅ **REQUIRED:** Use short morph types (e.g., `sales_invoice`).
* **Reason:** Cleaner database, easier refactoring.

---

## 3. Business Logic Rules

### A. Invoice Lifecycle (Draft vs Posted)

#### Status Enum
* **Required:** All commercial documents MUST have a `status` enum column:
    * `sales_invoices.status`: `['draft', 'posted']`
    * `purchase_invoices.status`: `['draft', 'posted']`
    * `stock_adjustments.status`: `['draft', 'posted']`
    * `warehouse_transfers.status`: `['draft', 'posted']`

#### Draft Status
* **Behavior:**
    * Saves data to the database
    * ❌ **NO effect** on `stock_movements`
    * ❌ **NO effect** on `treasury_transactions`
    * ❌ **NO effect** on partner balances
    * ✅ **Editable:** Can be modified or deleted
* **Use Case:** Allows users to prepare invoices without affecting inventory or finances.

#### Posted Status
* **Behavior:**
    * ✅ **Triggers Service Layer:**
        * `StockService::postSalesInvoice($invoice)`
        * `TreasuryService::postSalesInvoice($invoice)`
    * ✅ **Creates Ledger Entries:**
        * `stock_movements` records
        * `treasury_transactions` records
    * ✅ **Updates Balances:**
        * Partner `current_balance`
        * Product `avg_cost` (for purchases)
    * ❌ **Immutable:** Once posted, invoice cannot be edited or deleted
* **Use Case:** Finalizes the transaction and locks the record.

#### Posting Action
* **UI:** Filament Resources MUST have a "Post" action that:
    * Changes status from `draft` to `posted`
    * Calls the appropriate Service methods
    * Shows success/error notifications
* **Validation:** Before posting, validate:
    * Stock availability (for sales)
    * Required fields are filled
    * Invoice is in `draft` status

---

### B. Warehouse Scope

#### One Warehouse Per Invoice
* **Selection:** Warehouse is selected at the **Invoice Header** level.
* **Constraint:** All items in a single invoice MUST exit/enter the same warehouse.
* **Storage:**
    * `sales_invoices.warehouse_id`
    * `purchase_invoices.warehouse_id`
* **Reason:** Simplifies stock tracking and prevents cross-warehouse confusion.

#### Stock Movements
* Each `stock_movement` record MUST reference a `warehouse_id`.
* Stock levels are calculated per warehouse:
    * `StockService::getCurrentStock($productId, $warehouseId)`

---

### C. Product Cost & Price Updates

#### Average Cost Calculation
* **Trigger:** When a `purchase_invoice` is posted.
* **Method:** `StockService::updateProductAvgCost($productId)`
* **Formula:** Weighted average based on purchase quantities and costs.
* **Storage:** Updated in `products.avg_cost` (decimal 18,4).

#### Selling Price Updates
* **Feature:** Purchase invoice items can include `new_selling_price`.
* **Behavior:** When purchase invoice is posted:
    * If `new_selling_price` is set, update product prices immediately.
    * Method: `StockService::updateProductPrice($productId, $priceData)`

---

## 4. UI/UX Guidelines (Filament)

### A. Localization & RTL

#### Arabic Language Support
* **Locale:** Application locale MUST be set to `'ar'` in `config/app.php`.
* **Environment:** `.env` file MUST have `APP_LOCALE=ar`.
* **Fallback:** `APP_FALLBACK_LOCALE=ar`.

#### Right-to-Left (RTL) Layout
* **HTML Direction:** Layout automatically sets `dir="rtl"` when locale is Arabic.
* **Location:** `resources/views/vendor/filament-panels/components/layout/base.blade.php`
* **CSS:** Tailwind CSS handles RTL automatically when `dir="rtl"` is set.

#### Arabic Labels
* **Navigation:** All navigation labels MUST be in Arabic.
    * Example: `protected static ?string $navigationLabel = 'فواتير البيع';`
* **Form Fields:** All form field labels MUST be in Arabic.
    * Example: `->label('اسم المنتج')`
* **Actions:** All action labels MUST be in Arabic.
    * Example: `->label('نشر الفاتورة')`

---

### B. Form Design

#### Single-Page Resources
* ❌ **FORBIDDEN:** No multi-step wizards.
* ✅ **REQUIRED:** All forms MUST be single-page, scrollable resources.
* **Reason:** Faster data entry, better UX for retail operations.

#### POS-Style Entry
* **Product Selection:**
    * Barcode scanning support
    * Fast product search/autocomplete
    * Auto-pricing based on unit selection
* **Live Calculations:**
    * Item totals update automatically
    * Subtotal, discount, and grand total are reactive
    * Stock availability checks in real-time
* **Unit Selection:**
    * Dropdown to select "Small" or "Large" unit
    * Price updates based on selected unit
    * Quantity conversion handled automatically

#### Reactive Fields
* **Use Filament's Reactive Features:**
    * `->reactive()` for fields that trigger updates
    * `->afterStateUpdated()` for calculations
    * `->live()` for real-time validation

---

### C. Navigation & Organization

#### Navigation Groups (Arabic)
* **المخزون** (Inventory): Products, Warehouses, Stock Adjustments, Transfers
* **المبيعات** (Sales): Sales Invoices
* **المشتريات** (Purchases): Purchase Invoices
* **الشركاء** (Partners): Customers & Suppliers
* **المالية** (Finance): Treasury Transactions, Expenses, Revenues
* **التقارير** (Reports): Daily Operations, Profit/Loss, Item History
* **الإعدادات** (Settings): Units, Users

---

## 5. Service Layer Implementation

### A. StockService

#### Core Methods
* `recordMovement($warehouseId, $productId, $type, $quantity, $cost, $reference)`
    * Creates a `stock_movement` record
    * Quantity is always in Base Units
* `getCurrentStock($productId, $warehouseId): int`
    * Returns current stock level (sum of movements)
* `validateStockAvailability($productId, $warehouseId, $quantity): bool`
    * Checks if sufficient stock exists
* `postSalesInvoice(SalesInvoice $invoice): void`
    * Posts a sales invoice (creates movements, validates stock)
* `postPurchaseInvoice(PurchaseInvoice $invoice): void`
    * Posts a purchase invoice (creates movements, updates costs)
* `postStockAdjustment(StockAdjustment $adjustment): void`
    * Posts a stock adjustment
* `postWarehouseTransfer(WarehouseTransfer $transfer): void`
    * Posts a warehouse transfer (creates two movements: out + in)
* `updateProductAvgCost($productId): void`
    * Recalculates and updates product average cost
* `updateProductPrice($productId, $priceData): void`
    * Updates product selling prices

#### Transaction Wrapping
* All methods that create/update multiple records MUST use `DB::transaction()`.

---

### B. TreasuryService

#### Core Methods
* `recordTransaction($treasuryId, $type, $amount, $description, $partnerId, $referenceType, $referenceId)`
    * Creates a `treasury_transaction` record
* `updatePartnerBalance($partnerId, $amount): void`
    * Updates partner `current_balance`
* `getTreasuryBalance($treasuryId): decimal`
    * Returns current treasury balance (sum of transactions)
* `getPartnerBalance($partnerId): decimal`
    * Returns current partner balance
* `postSalesInvoice(SalesInvoice $invoice): void`
    * Posts a sales invoice (creates transactions, updates partner balance)
* `postPurchaseInvoice(PurchaseInvoice $invoice): void`
    * Posts a purchase invoice (creates transactions, updates partner balance)
* `recordFinancialTransaction(...): TreasuryTransaction`
    * Records a standalone financial transaction
* `postExpense(Expense $expense): void`
    * Posts an expense
* `postRevenue(Revenue $revenue): void`
    * Posts a revenue

#### Transaction Wrapping
* All methods that create/update multiple records MUST use `DB::transaction()`.

---

## 6. Model Conventions

### A. Required Traits

#### HasUlids
* **Required on:** All models with ULID primary keys.
* **Usage:** `use HasUlids;`

#### SoftDeletes
* **Required on:** Core business models (see Section 2.C).
* **Usage:** `use SoftDeletes;`

---

### B. Model Methods

#### Status Helpers
* **Required:** Models with `status` enum MUST have helper methods:
    * `isDraft(): bool`
    * `isPosted(): bool`
* **Example:**
    ```php
    public function isDraft(): bool
    {
        return $this->status === SalesInvoiceStatus::Draft;
    }
    
    public function isPosted(): bool
    {
        return $this->status === SalesInvoiceStatus::Posted;
    }
    ```

#### Unit Conversion
* **Product Model:** MUST have `convertToBaseUnit($quantity, $unitType): int`
* **Usage:** Converts UI quantity to Base Unit for storage.

---

## 7. Filament Resource Conventions

### A. Resource Structure

#### Required Properties
* `protected static ?string $navigationIcon`
* `protected static ?string $navigationGroup` (Arabic)
* `protected static ?string $navigationLabel` (Arabic)
* `protected static ?string $modelLabel` (Arabic)
* `protected static ?string $pluralModelLabel` (Arabic)

#### Form Schema
* Use sections for logical grouping
* Reactive fields for live calculations
* Validation rules for data integrity
* Help text in Arabic

#### Table Schema
* Display calculated fields (e.g., stock levels, balances)
* Color coding for status (draft = yellow, posted = green)
* Filters for common queries
* Actions: Edit (draft only), Delete (draft only), Post

---

### B. Actions

#### Post Action
* **Visibility:** Only show for `draft` status invoices.
* **Behavior:**
    * Change status to `posted`
    * Call Service methods
    * Show success notification
    * Redirect to list view

#### Edit/Delete Actions
* **Visibility:** Only show for `draft` status invoices.
* **Reason:** Posted invoices are immutable.

---

## 8. Reporting Rules

### A. Data Sources

#### Stock Reports
* **Source:** `stock_movements` table ONLY.
* ❌ **FORBIDDEN:** Do NOT query invoices for stock calculations.

#### Financial Reports
* **Source:** `treasury_transactions` table ONLY.
* ❌ **FORBIDDEN:** Do NOT query invoices for financial calculations.

#### Profit/Loss Reports
* **Sales:** Sum of `treasury_transactions` where `type = 'collection'`
* **COGS:** Sum of `stock_movements` where `type = 'sale'` (quantity * cost_at_time)
* **Expenses:** Sum of `treasury_transactions` where `type = 'expense'`

---

## 9. Coding Style & Best Practices

### A. PHP Standards

#### Version
* **PHP Version:** 8.2+ required.
* **Features:** Use modern PHP features:
    * Typed properties
    * Enums for status values
    * Named arguments
    * Match expressions

#### Type Hints
* **Preferred:** Strict typing (`declare(strict_types=1);`).
* **Required:** Type hints for all method parameters and return types.

#### Code Organization
* **Controllers/Resources:** Keep slim; push logic to Services.
* **Services:** Contain all business logic.
* **Models:** Only contain relationships, accessors, mutators, and simple helpers.

---

### B. Error Handling

#### Validation
* **Form Level:** Use Filament's built-in validation.
* **Service Level:** Throw exceptions for business rule violations.
* **Example:** `throw new \Exception('Insufficient stock available');`

#### Transactions
* **Rollback:** If any step fails in a transaction, all changes rollback automatically.
* **Error Messages:** Provide clear, Arabic error messages to users.

---

## 10. Testing & Quality Assurance

### A. Data Integrity Tests

#### Required Tests
* Stock movements are created correctly when invoice is posted.
* Treasury transactions are created correctly when invoice is posted.
* Partner balances update correctly.
* Product costs update correctly on purchase.
* Draft invoices do not affect stock or treasury.
* Posted invoices cannot be edited.

---

## 11. Future Considerations

### A. Offline/Sync Capability
* **ULIDs:** Already implemented for offline-ready primary keys.
* **Future:** May implement sync mechanism for multi-device support.

### B. Multi-Warehouse
* **Current:** One warehouse per invoice.
* **Future:** May support multi-warehouse operations.

---

## 12. Quick Reference Checklist

When implementing a new feature, ensure:

- [ ] Uses ULIDs for primary keys
- [ ] Uses `decimal(18, 4)` for money
- [ ] Uses `integer` for quantities
- [ ] Has soft deletes if it's a core model
- [ ] Service methods wrapped in `DB::transaction()`
- [ ] Uses Service Layer, NOT Observers
- [ ] Queries ledgers (`stock_movements`, `treasury_transactions`) for reports
- [ ] Has `draft`/`posted` status if it's a commercial document
- [ ] All UI labels in Arabic
- [ ] RTL layout works correctly
- [ ] Single-page form (no wizards)
- [ ] Reactive fields for live calculations
- [ ] Stock calculations in Base Units
- [ ] MorphMap entry if using polymorphism

---

## 13. Critical Rules from Production Bug Fixes

> **Added:** December 29, 2025
> **Purpose:** Document lessons learned from real bugs discovered during testing and refactoring. These rules PREVENT critical financial errors.

---

### A. Financial Precision Rule

**Discovery Date:** December 28, 2025
**Root Cause:** DECIMAL(10,2) precision insufficient for fractional currency values
**Impact:** Very small values (0.001 EGP) became 0.0 in database

#### The Rule (NON-NEGOTIABLE)

* **ALL financial columns in database MUST be `DECIMAL(15, 4)`**
* ❌ **FORBIDDEN:** Never use `float` or `double` for monetary values
* ❌ **FORBIDDEN:** Never use `DECIMAL(10, 2)` for financial data
* ✅ **REQUIRED:** Use `DECIMAL(15, 4)` for:
    * Product prices and costs (`retail_price`, `wholesale_price`, `avg_cost`)
    * Invoice totals (`subtotal`, `total`, `discount`)
    * Stock movement costs (`cost_at_time`)
    * Treasury amounts (`amount`)
    * Partner balances (`current_balance`)
    * Payment amounts
    * All other monetary values

#### Migration Reference
* **File:** `database/migrations/2025_12_28_183925_increase_decimal_precision_for_financial_columns.php`
* **Scope:** 40+ columns across 15 tables
* **Supports:** Values up to 999,999,999,999.9999

#### PHP Calculation Rule
* **For high-precision calculations:** Use `bcmath` functions or high-precision casting
* **For Laravel Models:** Cast monetary attributes to `'decimal:4'`
* **Example:**
    ```php
    protected function casts(): array
    {
        return [
            'avg_cost' => 'decimal:4',
            'retail_price' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }
    ```

---

### B. Weighted Average Cost Calculation Rule

**Discovery Date:** December 28, 2025
**Root Cause:** Large unit purchases stored large unit cost instead of base unit cost
**Impact:** CRITICAL - Incorrect COGS, gross profit, and inventory valuation

#### The Bug Example
```
Purchase: 5 Cartons @ 600 EGP/carton (factor = 12 pieces/carton)
Expected cost_at_time: 600 ÷ 12 = 50 EGP per piece
Actual (BUG): 600 EGP per piece stored
Result: avg_cost = 600 instead of 50 (12x inflated!)
```

#### The Rule (NON-NEGOTIABLE)

* **When purchasing in "Large Units" (Cartons, Boxes, etc.), ALWAYS divide unit cost by the conversion factor BEFORE storing `cost_at_time`**
* ❌ **FORBIDDEN:** Storing large unit cost directly in `stock_movements.cost_at_time`
* ✅ **REQUIRED:** Store BASE UNIT cost in `cost_at_time`

#### Implementation Pattern
```php
// In StockService::postPurchaseInvoice()
foreach ($invoice->items as $item) {
    $product = Product::find($item->product_id);

    // CRITICAL: Convert cost to base unit
    $baseUnitCost = $item->unit_type === 'large' && $product->factor > 1
        ? $item->unit_cost / $product->factor
        : $item->unit_cost;

    StockMovement::create([
        'cost_at_time' => $baseUnitCost, // ← Use base unit cost
        'quantity' => $baseUnitQuantity,
        // ... other fields
    ]);
}
```

#### Validation Rule
* **Before posting purchase invoice:** Assert that `cost_at_time` is reasonable for base unit
* **Add test:** Verify weighted average after large unit purchase matches expected base unit cost

---

### C. Double-Entry Accounting Rule

**Discovery Date:** Architectural foundation
**Root Cause:** Financial systems require balanced transactions
**Impact:** CRITICAL - Affects all financial reporting and partner balances

#### The Rule (NON-NEGOTIABLE)

* **Double Entry is Law:** Every financial transaction MUST have equal and opposite Debit and Credit effects
* **No Exceptions:** This applies to ALL treasury transactions involving partners

#### Implementation Pattern
```php
// Sales Invoice (Cash): 10,000 EGP
Treasury (+10,000)      // Debit: Cash increases
Partner Balance (-10,000) // Credit: Customer owes less (or we owe them refund)

// Purchase Invoice (Cash): 5,000 EGP
Treasury (-5,000)        // Credit: Cash decreases
Partner Balance (+5,000)  // Debit: Supplier is owed more
```

#### Service Implementation
```php
public function recordFinancialTransaction(...): TreasuryTransaction
{
    DB::transaction(function () {
        // 1. Create treasury transaction (affects cash)
        $transaction = TreasuryTransaction::create([
            'type' => $type,
            'amount' => $amount,
            // ...
        ]);

        // 2. Update partner balance (opposite effect)
        if ($partnerId) {
            $this->updatePartnerBalance($partnerId, $amount, $type);
        }

        return $transaction;
    });
}
```

#### Validation Rule
* **Before commit:** Sum of debits MUST equal sum of credits
* **Add test:** Verify partner balance + treasury balance = expected total

---

### D. Atomicity & Transaction Wrapping Rule

**Discovery Date:** Architectural foundation
**Root Cause:** Multi-step operations can fail partially
**Impact:** CRITICAL - Prevents data corruption and inconsistent state

#### The Rule (NON-NEGOTIABLE)

* **ALL multi-step financial operations MUST be wrapped in `DB::transaction()`**
* **No Exceptions:** This includes:
    * Invoice posting (Stock + Treasury + Partner Balance)
    * Return processing (Stock + Treasury + Partner Balance)
    * Payment recording (Treasury + Partner Balance + Invoice Update)
    * Stock adjustments (Stock + Audit Trail)
    * Warehouse transfers (Stock Out + Stock In)

#### Implementation Pattern
```php
public function postSalesInvoice(SalesInvoice $invoice): void
{
    DB::transaction(function () use ($invoice) {
        // Step 1: Create stock movements (may fail due to insufficient stock)
        foreach ($invoice->items as $item) {
            $this->validateStockAvailability(...);
            $this->recordMovement(...);
        }

        // Step 2: Create treasury transactions
        $this->treasuryService->recordTransaction(...);

        // Step 3: Update partner balance
        $this->treasuryService->updatePartnerBalance(...);

        // Step 4: Mark invoice as posted
        $invoice->update(['status' => 'posted']);

        // If ANY step fails, ALL steps rollback
    });
}
```

#### Nested Transaction Handling
```php
// Only wrap in transaction if not already in one
if (DB::transactionLevel() === 0) {
    DB::transaction($execute);
} else {
    $execute();
}
```

#### Testing Rule
* **Add test:** Verify that when one step fails (e.g., insufficient stock), NO changes persist
* **Add test:** Verify database state is unchanged after exception

---

### E. Inventory Costing Rule

**Discovery Date:** December 28, 2025
**Root Cause:** Weighted average cost depends on correct cost_at_time values
**Impact:** CRITICAL - Affects COGS, gross profit calculations, and financial statements

#### The Rule (NON-NEGOTIABLE)

* **Weighted Average Cost Method MUST be used for all inventory costing**
* **Formula:** `avg_cost = (sum of quantity × cost_at_time) / total_quantity`
* **Recalculation Trigger:** MUST recalculate avg_cost after EVERY purchase posting
* **Validation:** MUST prevent negative stock (unless explicitly configured via settings)

#### Implementation Requirements
* **Purchase Posting:**
    1. Create stock movement with correct `cost_at_time` (base unit cost)
    2. Recalculate product `avg_cost` using `StockService::updateProductAvgCost()`
    3. Update product selling prices if `new_selling_price` is set

* **Sales Posting:**
    1. Record `cost_at_time` = current product `avg_cost` at time of sale
    2. Do NOT recalculate avg_cost (sales don't affect average cost)
    3. Use `cost_at_time` for COGS reporting

#### Cost Flow Example
```
Purchase 1: 100 units @ 10 EGP = 1,000 EGP → avg_cost = 10.00
Purchase 2: 50 units @ 12 EGP = 600 EGP   → avg_cost = 10.67
Sale:       -30 units @ 10.67 EGP (COGS)  → avg_cost = 10.67 (unchanged)
Purchase 3: 20 units @ 15 EGP = 300 EGP   → avg_cost = 11.07
```

#### Validation Rules
* **Before Sales Posting:** Check `product.avg_cost > 0` (prevents selling zero-cost items)
* **After Purchase Posting:** Verify `product.avg_cost` updated correctly
* **Add test:** Verify weighted average after mixed purchases/sales

---

### F. Configuration vs Financial Data Separation Rule

**Discovery Date:** December 29, 2025
**Root Cause:** Fixed assets stored in settings were financial data, not configuration
**Impact:** MEDIUM - Mixed concerns, difficult to audit, no treasury linkage

#### The Rule (NON-NEGOTIABLE)

* **Configuration goes to Spatie Settings**
* **Financial Assets go to FixedAsset models**
* ❌ **FORBIDDEN:** Storing monetary amounts in settings tables
* ❌ **FORBIDDEN:** Mixing configuration with transactional data

#### What Belongs in Settings (Spatie)
* Company information (name, address, tax number)
* Currency settings (currency code, symbol)
* System toggles (enable_multi_warehouse, allow_negative_stock)
* Document prefixes (invoice numbering)
* Default values (payment terms days, low stock threshold)
* UI preferences

#### What Belongs in Models (Database Tables)
* Financial assets (Fixed Assets, Inventory)
* Transactions (Invoices, Payments, Stock Movements)
* Partner balances
* Historical data requiring audit trail
* Data with relationships (e.g., FixedAsset → Treasury)

#### Migration Example
**Before (WRONG):**
```php
// In settings table
'fixed_assets_value' => 50000.00  // BAD: No detail, no treasury link
```

**After (CORRECT):**
```php
// FixedAsset model
FixedAsset::create([
    'name' => 'Office Furniture',
    'purchase_amount' => 15000.00,
    'treasury_id' => $treasury->id,
    'purchase_date' => '2025-01-15',
    'created_by' => $user->id,
]);
// Each asset is a separate record with full audit trail
```

---

### G. Filament UI Best Practices Rule

**Discovery Date:** December 28, 2025
**Root Cause:** Custom HTML in activity log was harder to maintain than native components
**Impact:** LOW - Maintenance overhead, inconsistent UI

#### The Rule (RECOMMENDED)

* **Use Native Filament Components instead of custom HTML whenever possible**
* **Only use custom HTML when Filament components cannot achieve the requirement**

#### Component Preference Order
1. **First:** Try native Filament components (TextEntry, KeyValueEntry, etc.)
2. **Second:** Try Filament's ViewColumn with Blade partials
3. **Last Resort:** Custom HTML via TextEntry with formatStateUsing

#### Example Refactor
**Before (Custom HTML):**
```php
TextEntry::make('properties')
    ->formatStateUsing(function ($record) {
        $html = '<div class="space-y-1">';
        foreach ($record->properties as $key => $value) {
            $html .= "<div><strong>{$key}:</strong> {$value}</div>";
        }
        $html .= '</div>';
        return new HtmlString($html);
    })
```

**After (Native Component):**
```php
KeyValueEntry::make('properties')
    ->label('التغييرات')
    ->keyLabel('الحقل')
    ->valueLabel('القيمة')
```

#### Benefits
* ✅ Consistent styling with Filament theme
* ✅ Automatic dark mode support
* ✅ Better accessibility
* ✅ Easier maintenance
* ✅ Type safety

---

### H. Testing Best Practices Rule

**Discovery Date:** December 28, 2025
**Root Cause:** Comprehensive tests caught critical weighted average bug
**Impact:** HIGH - Prevented production financial errors

#### The Rule (NON-NEGOTIABLE)

* **New Logic = New Test. No PR is accepted without test coverage for business logic changes.**
* **All Service Layer methods MUST have feature tests**
* **All financial calculations MUST have assertion tests**

#### Required Test Coverage
* ✅ **Stock Service Tests:**
    * Weighted average cost calculations (all scenarios)
    * Stock validation (negative stock prevention)
    * Unit conversion (small/large unit purchases and sales)
    * Stock movement creation (all transaction types)

* ✅ **Treasury Service Tests:**
    * Partner balance updates (all transaction types)
    * Treasury balance calculations
    * Double-entry validation (debits = credits)
    * Payment allocation (partial payments, discounts)

* ✅ **Invoice Posting Tests:**
    * Draft invoices do not affect stock/treasury
    * Posted invoices create correct ledger entries
    * Posted invoices cannot be edited/deleted
    * Atomicity (rollback on error)

* ✅ **Edge Case Tests:**
    * Boundary values (0, negative, very large, very small)
    * Stress tests (50+ item invoices, million-dollar amounts)
    * Concurrent updates (same product from multiple invoices)
    * Return edge cases (exceeding original quantity, different prices)

#### Test Naming Convention
```php
// ✅ GOOD: Descriptive, explains business rule
test_weighted_average_with_large_unit_purchases()
test_customer_advance_payment_creates_negative_balance()

// ❌ BAD: Vague, doesn't explain what's being tested
test_purchase_invoice()
test_customer_balance()
```

#### Assertion Best Practices
```php
// ✅ GOOD: Explains WHY expected value is correct
$this->assertEquals(50, $product->avg_cost,
    'Expected: (60 units * 50 EGP) / 60 units = 50 EGP per piece'
);

// ❌ BAD: No explanation
$this->assertEquals(50, $product->avg_cost);
```

---

### I. Navigation & Organization Rule

**Discovery Date:** Architectural foundation
**Root Cause:** Consistent navigation improves UX
**Impact:** MEDIUM - User experience and discoverability

#### The Rule (RECOMMENDED)

* **Group related resources logically using Filament navigation groups**
* **Use Arabic navigation labels consistently**
* **Follow the established grouping pattern**

#### Standard Navigation Groups
```php
protected static ?string $navigationGroup = 'المخزون';    // Inventory
protected static ?string $navigationGroup = 'الشركاء';    // Partners
protected static ?string $navigationGroup = 'المبيعات';   // Sales
protected static ?string $navigationGroup = 'المشتريات';  // Purchases
protected static ?string $navigationGroup = 'المالية';    // Finance
protected static ?string $navigationGroup = 'التقارير';   // Reports
protected static ?string $navigationGroup = 'الإدارة';    // Management
```

#### Grouping Logic
* **Assets:** Fixed Assets, Equipment (not in Finance)
* **Finance:** Treasury, Transactions, Expenses, Revenues (not Assets)
* **Management:** Users, Settings, Activity Log, System Configuration
* **Inventory:** Products, Warehouses, Stock Adjustments, Transfers
* **Partners:** Customers, Suppliers, Shareholders, Employees

#### Navigation Sort Order
```php
protected static ?int $navigationSort = 10; // Lower number = higher in menu
```

---

## 14. Quick Reference: Bug Prevention Checklist

When implementing new financial features, verify:

- [ ] ✅ All monetary columns are `DECIMAL(15, 4)`
- [ ] ✅ Large unit purchases divide cost by factor before storing
- [ ] ✅ Weighted average recalculated after EVERY purchase
- [ ] ✅ Double-entry effects applied (Treasury + Partner Balance)
- [ ] ✅ Multi-step operations wrapped in `DB::transaction()`
- [ ] ✅ Service methods used (NOT Model Observers)
- [ ] ✅ Posted invoices are immutable (cannot edit/delete)
- [ ] ✅ Draft invoices do not affect stock/treasury
- [ ] ✅ Base units used for all stock storage
- [ ] ✅ Configuration in Settings, financial data in Models
- [ ] ✅ Native Filament components used (not custom HTML)
- [ ] ✅ Feature tests written for business logic
- [ ] ✅ Edge cases tested (boundary values, stress tests)
- [ ] ✅ Arabic labels used consistently
- [ ] ✅ Navigation grouped logically

---

**End of Document**

