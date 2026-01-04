# 🔍 Comprehensive Code Audit Report
**Date:** Generated on Audit  
**Scope:** `app/` directory (Models, Resources, Services)  
**Focus:** Performance, Data Integrity, Code Quality

---

## 🔴 CRITICAL (Must Fix Now)

### 1. **N+1 Query: Computed Accessors in Invoice Models**
**File:** `app/Models/PurchaseInvoice.php`, `app/Models/SalesInvoice.php`  
**Issue:** The `getTotalPaidAttribute()` accessor performs a database query (`$this->payments()->sum('amount')`) every time it's accessed. If used in table listings or loops, this causes N+1 queries.

**Lines:**
- `PurchaseInvoice.php:88-91`
- `SalesInvoice.php:88-91`

**Solution:**
```php
// Option 1: Add eager loading in Resource
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => $query->with('payments'))
        // ... rest of table config
}

// Option 2: Cache the value (better for single record views)
public function getTotalPaidAttribute(): float
{
    if (!$this->relationLoaded('payments')) {
        $this->load('payments');
    }
    return floatval($this->paid_amount) + $this->payments->sum('amount');
}
```

---

### 2. **N+1 Query: Relationship Access in Table Columns Without Eager Loading**
**Files:** Multiple Resources  
**Issue:** Table columns access relationships (e.g., `partner.name`, `warehouse.name`) without eager loading, causing N+1 queries.

**Affected Resources:**
- `SalesInvoiceResource.php:482-488` - `partner.name`, `warehouse.name`
- `PurchaseInvoiceResource.php:430-436` - `partner.name`, `warehouse.name`
- `ProductResource.php:234-240` - `smallUnit.name`, `largeUnit.name`
- `StockMovementResource.php:80-86` - `warehouse.name`, `product.name`
- `TreasuryTransactionResource.php:198-210` - `treasury.name`, `partner.name`, `employee.name`

**Solution:**
Add `modifyQueryUsing()` to each Resource's `table()` method:

```php
// Example for SalesInvoiceResource
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => $query->with(['partner', 'warehouse', 'creator']))
        ->columns([
            // ... columns
        ]);
}

// Example for ProductResource
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => $query->with(['smallUnit', 'largeUnit']))
        ->columns([
            // ... columns
        ]);
}

// Example for StockMovementResource
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => $query->with(['warehouse', 'product']))
        ->columns([
            // ... columns
        ]);
}

// Example for TreasuryTransactionResource
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => $query->with(['treasury', 'partner', 'employee']))
        ->columns([
            // ... columns
        ]);
}
```

---

### 3. **Race Condition: Stock Validation Window**
**File:** `app/Services/StockService.php:65-69, 184-186`  
**Issue:** Stock availability is checked before posting, but there's a time window between the check and the actual stock deduction where concurrent requests could cause negative stock.

**Lines:** `StockService.php:184-186`

**Solution:**
Use database-level locking or optimistic locking:

```php
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
            $baseQuantity = $this->convertToBaseUnit($product, $item->quantity, $item->unit_type);

            // Re-validate stock inside transaction with lock
            $currentStock = StockMovement::where('warehouse_id', $invoice->warehouse_id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->sum('quantity');

            if ($currentStock < $baseQuantity) {
                throw new \Exception("المخزون غير كافٍ للمنتج: {$product->name}");
            }

            // Create negative stock movement
            $this->recordMovement(
                $invoice->warehouse_id,
                $product->id,
                'sale',
                -$baseQuantity,
                $product->avg_cost,
                'sales_invoice',
                $invoice->id
            );
        }
    });
}
```

---

### 4. **Missing Deletion Protection: Partner Model**
**File:** `app/Filament/Resources/PartnerResource.php:240`  
**Issue:** `DeleteAction` doesn't check for related invoices, transactions, or payments before deletion. Deleting a partner with financial history causes data integrity issues.

**Solution:**
Add deletion protection in `Partner` model or Resource:

```php
// In Partner model booted() method
protected static function booted(): void
{
    static::deleting(function (Partner $partner) {
        $hasInvoices = $partner->salesInvoices()->exists() 
            || $partner->purchaseInvoices()->exists();
        $hasReturns = $partner->salesReturns()->exists() 
            || $partner->purchaseReturns()->exists();
        $hasTransactions = $partner->treasuryTransactions()->exists();
        $hasPayments = $partner->invoicePayments()->exists();

        if ($hasInvoices || $hasReturns || $hasTransactions || $hasPayments) {
            throw new \Exception('لا يمكن حذف الشريك لوجود فواتير أو معاملات مالية مرتبطة به. يمكنك تعطيله بدلاً من ذلك.');
        }
    });
}
```

---

### 5. **Missing Deletion Protection: Product Model**
**File:** `app/Filament/Resources/ProductResource.php:333`  
**Issue:** `DeleteAction` doesn't check for related invoices, stock movements, or adjustments before deletion.

**Solution:**
Add deletion protection:

```php
// In Product model booted() method
protected static function booted(): void
{
    // ... existing code ...
    
    static::deleting(function (Product $product) {
        $hasStockMovements = \App\Models\StockMovement::where('product_id', $product->id)->exists();
        $hasInvoiceItems = \App\Models\SalesInvoiceItem::where('product_id', $product->id)->exists()
            || \App\Models\PurchaseInvoiceItem::where('product_id', $product->id)->exists();
        $hasAdjustments = \App\Models\StockAdjustment::where('product_id', $product->id)->exists();

        if ($hasStockMovements || $hasInvoiceItems || $hasAdjustments) {
            throw new \Exception('لا يمكن حذف المنتج لوجود فواتير أو حركات مخزون مرتبطة به. يمكنك تعطيله باستخدام Soft Delete.');
        }
    });
}
```

---

### 6. **N+1 Query: Global Search Implementation**
**File:** `app/Models/SalesInvoice.php:177-189`, `app/Models/PurchaseInvoice.php:177-189`  
**Issue:** `getGlobalSearchResultTitle()` and `getGlobalSearchResultDetails()` access `$this->partner->name` without ensuring the relationship is loaded, causing N+1 queries in search results.

**Solution:**
Ensure relationship is loaded or use `$this->partner?->name ?? 'N/A'` with proper null handling, or eager load in search query.

---

## 🟠 PERFORMANCE (Fix Soon)

### 7. **Heavy Query: Product::all() in Financial Report**
**File:** `app/Services/FinancialReportService.php:95`  
**Issue:** `Product::all()` loads all products into memory, then loops through them to calculate inventory. For large product catalogs, this is inefficient.

**Lines:** `FinancialReportService.php:95-117`

**Solution:**
Use chunking or a single optimized query:

```php
protected function calculateInventoryValue(string $date, bool $exclusive = false): float
{
    $dateOperator = $exclusive ? '<' : '<=';
    
    // Single optimized query instead of loading all products
    $totalValue = DB::table('products')
        ->join('stock_movements', 'products.id', '=', 'stock_movements.product_id')
        ->where('stock_movements.created_at', $dateOperator, $date)
        ->selectRaw('SUM(stock_movements.quantity * products.avg_cost) as total_value')
        ->where('products.avg_cost', '>', 0)
        ->groupBy('products.id')
        ->havingRaw('SUM(stock_movements.quantity) > 0')
        ->get()
        ->sum('total_value');

    return (float) $totalValue;
}
```

---

### 8. **N+1 Query: Treasury Balance Calculation**
**File:** `app/Services/FinancialReportService.php:146-154`  
**Issue:** Loops through all treasuries and calls `getTreasuryBalance()` for each, which performs a separate query.

**Solution:**
Use a single aggregated query:

```php
protected function calculateTotalCash(): float
{
    return (float) TreasuryTransaction::sum('amount');
}
```

**Note:** This assumes all transactions are in the same currency/unit. If treasuries need separate calculation, use:

```php
protected function calculateTotalCash(): float
{
    return (float) DB::table('treasury_transactions')
        ->selectRaw('SUM(amount) as total')
        ->value('total') ?? 0;
}
```

---

### 9. **N+1 Query: Product Stock Calculation in Table**
**File:** `app/Filament/Resources/ProductResource.php:249-256`  
**Issue:** `getStateUsing()` performs a separate DB query for each product row in the table.

**Solution:**
Use a subquery or join:

```php
Tables\Columns\TextColumn::make('total_stock')
    ->label('إجمالي المخزون')
    ->state(function (Product $record) {
        // This still causes N+1, but we can optimize with eager loading
        return $record->stock_movements_sum ?? 0;
    })
    ->sortable(query: function ($query, string $direction) {
        return $query->withSum('stockMovements', 'quantity')
            ->orderBy('stock_movements_sum_quantity', $direction);
    })
    ->badge()
    ->color(function ($state, Product $record) {
        if ($state < 0) return 'danger';
        if ($state < ($record->min_stock ?? 0)) return 'warning';
        return 'success';
    }),
```

Better approach - use `withSum()` in query:

```php
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => $query->withSum('stockMovements', 'quantity'))
        ->columns([
            // ...
            Tables\Columns\TextColumn::make('stock_movements_sum_quantity')
                ->label('إجمالي المخزون')
                ->sortable()
                ->badge()
                ->color(function ($state, Product $record) {
                    if ($state < 0) return 'danger';
                    if ($state < ($record->min_stock ?? 0)) return 'warning';
                    return 'success';
                }),
        ]);
}
```

---

### 10. **N+1 Query: Warehouse Stock Calculation**
**File:** `app/Filament/Resources/WarehouseResource.php:76-89`  
**Issue:** `getStateUsing()` performs a separate query for each warehouse row.

**Solution:**
Use `withSum()`:

```php
public static function table(Table $table): Table
{
    return $table
        ->modifyQueryUsing(fn ($query) => $query->withSum('stockMovements', 'quantity'))
        ->columns([
            // ...
            Tables\Columns\TextColumn::make('stock_movements_sum_quantity')
                ->label('إجمالي الأصناف')
                ->sortable()
                ->badge()
                ->color(function ($state) {
                    if ($state < 0) return 'danger';
                    if ($state == 0) return 'warning';
                    return 'success';
                }),
        ]);
}
```

---

### 11. **Inefficient Select Options: Treasury::pluck()**
**File:** `app/Filament/Resources/SalesInvoiceResource.php:640`, `app/Filament/Resources/PurchaseInvoiceResource.php:601`  
**Issue:** `Treasury::pluck('name', 'id')` loads all treasuries into memory. While usually fine, for large datasets use `relationship()` instead.

**Solution:**
Already using `relationship()` in some places, but ensure consistency:

```php
Forms\Components\Select::make('treasury_id')
    ->label('الخزينة')
    ->relationship('treasury', 'name') // Better than pluck
    ->required()
    ->searchable()
    ->preload(),
```

---

### 12. **Missing Database Indexes**
**Issue:** Frequently queried columns may lack indexes, causing slow queries.

**Recommendation:** Add indexes for:
- `stock_movements(warehouse_id, product_id)` - composite index for stock queries
- `stock_movements(product_id, created_at)` - for inventory calculations
- `treasury_transactions(treasury_id, created_at)` - for balance calculations
- `sales_invoices(partner_id, status)` - for partner balance calculations
- `purchase_invoices(partner_id, status)` - for partner balance calculations

**Solution:**
Create a migration:

```php
Schema::table('stock_movements', function (Blueprint $table) {
    $table->index(['warehouse_id', 'product_id'], 'idx_warehouse_product');
    $table->index(['product_id', 'created_at'], 'idx_product_date');
});

Schema::table('treasury_transactions', function (Blueprint $table) {
    $table->index(['treasury_id', 'created_at'], 'idx_treasury_date');
});

Schema::table('sales_invoices', function (Blueprint $table) {
    $table->index(['partner_id', 'status'], 'idx_partner_status');
});

Schema::table('purchase_invoices', function (Blueprint $table) {
    $table->index(['partner_id', 'status'], 'idx_partner_status');
});
```

---

## 🟢 REFACTOR (Nice to Have)

### 13. **Hardcoded Treasury Creation**
**File:** `app/Services/TreasuryService.php:138-149`  
**Issue:** Hardcoded Arabic name "الخزينة الرئيسية" in service logic.

**Solution:**
Move to config or use a constant:

```php
private const DEFAULT_TREASURY_NAME = 'الخزينة الرئيسية';

private function getDefaultTreasury(): string
{
    $treasury = \App\Models\Treasury::first();
    if (!$treasury) {
        $treasury = \App\Models\Treasury::create([
            'name' => config('app.default_treasury_name', self::DEFAULT_TREASURY_NAME),
            'type' => 'cash',
        ]);
    }
    return $treasury->id;
}
```

---

### 14. **Fat Resource: Complex Logic in Table Actions**
**File:** `app/Filament/Resources/SalesInvoiceResource.php:572-606`  
**Issue:** Complex posting logic is embedded in table action closure. Should be extracted to a dedicated service method.

**Solution:**
Create `InvoicePostingService`:

```php
// app/Services/InvoicePostingService.php
class InvoicePostingService
{
    public function __construct(
        private StockService $stockService,
        private TreasuryService $treasuryService
    ) {}

    public function postSalesInvoice(SalesInvoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $this->stockService->postSalesInvoice($invoice);
            $this->treasuryService->postSalesInvoice($invoice);
            $invoice->update(['status' => 'posted']);
        });
    }
}

// In Resource
Tables\Actions\Action::make('post')
    ->action(function (SalesInvoice $record) {
        try {
            app(InvoicePostingService::class)->postSalesInvoice($record);
            Notification::make()->success()->title('تم تأكيد الفاتورة بنجاح')->send();
        } catch (\Exception $e) {
            Notification::make()->danger()->title('خطأ')->body($e->getMessage())->send();
        }
    })
```

---

### 15. **Inconsistent Error Messages**
**Issue:** Error messages mix Arabic and English. Some are in Arabic, some in English.

**Recommendation:** Standardize on Arabic for user-facing messages, English for technical/logging.

---

### 16. **Missing Type Hints in Closures**
**File:** Multiple Resources  
**Issue:** Some closures lack proper type hints, making code less maintainable.

**Example:**
```php
// Current
->afterStateUpdated(function ($state, Set $set, Get $get) {

// Better
->afterStateUpdated(function ($state, Set $set, Get $get): void {
```

---

### 17. **Potential Division by Zero**
**File:** `app/Models/SalesInvoiceItem.php:46-52`, `app/Models/PurchaseInvoiceItem.php:50-56`  
**Issue:** Division by `$this->quantity` without checking if it's zero (though there's a check, it returns early).

**Current Code is Safe:** The check `if ($this->quantity <= 0)` prevents division by zero, but consider using `max(1, $this->quantity)` for extra safety.

---

### 18. **Unused/Dead Code**
**File:** `app/Services/FinancialReportService.php`  
**Issue:** Some methods may be unused. Review and remove if not needed.

**Recommendation:** Run static analysis to identify unused methods.

---

### 19. **Magic Numbers/Strings**
**File:** Multiple files  
**Issue:** Hardcoded status values like `'posted'`, `'draft'` scattered throughout code.

**Solution:**
Use constants or enums:

```php
// In SalesInvoice model
public const STATUS_DRAFT = 'draft';
public const STATUS_POSTED = 'posted';

// Usage
if ($invoice->status === SalesInvoice::STATUS_POSTED) {
    // ...
}
```

---

### 20. **Missing Validation: Negative Treasury Balance**
**File:** `app/Services/TreasuryService.php`  
**Issue:** No validation to prevent treasury balance from going negative (unless that's intentional).

**Solution:**
Add validation if negative balances should be prevented:

```php
public function recordTransaction(...): TreasuryTransaction
{
    return DB::transaction(function () use (...) {
        $transaction = TreasuryTransaction::create([...]);
        
        $newBalance = $this->getTreasuryBalance($treasuryId);
        if ($newBalance < 0 && config('treasury.prevent_negative_balance', false)) {
            throw new \Exception('الرصيد لا يمكن أن يكون سالباً');
        }
        
        return $transaction;
    });
}
```

---

## 📊 Summary Statistics

- **Critical Issues:** 6
- **Performance Issues:** 6
- **Refactoring Opportunities:** 8
- **Total Issues Found:** 20

---

## 🎯 Priority Recommendations

1. **Immediate (This Week):**
   - Fix N+1 queries in Resources (Issues #1, #2)
   - Add deletion protection for Partner and Product (Issues #4, #5)
   - Fix race condition in stock validation (Issue #3)

2. **Short Term (This Month):**
   - Optimize FinancialReportService queries (Issues #7, #8)
   - Add database indexes (Issue #12)
   - Fix stock calculation N+1 queries (Issues #9, #10)

3. **Long Term (Next Quarter):**
   - Extract complex logic to services (Issue #14)
   - Standardize error messages (Issue #15)
   - Add constants for magic strings (Issue #19)

---

**Report Generated By:** Senior Laravel & Filament Architect  
**Next Review:** After implementing critical fixes

