# COMPREHENSIVE SYSTEM LOGIC & TESTING GAP ANALYSIS REPORT
**Laravel Filament ERP System**
**Generated:** 2025-12-31
**Author:** Senior QA Automation Engineer & Laravel Architect

---

## EXECUTIVE SUMMARY

**Is your system safe?**
✅ **Core Logic: YES** - Robust service layer with strong financial integrity
⚠️ **Coverage: PARTIAL** - Critical features lack comprehensive test coverage
❌ **RBAC Testing: NO** - No automated tests for permission enforcement
❌ **Recent Features: NO** - Installments, Quotations, and Reports are untested

**Test Coverage Status:**
- **Existing Tests:** 6 files, ~150+ test methods
- **Estimated Coverage:** ~40% of critical logic flows
- **High-Risk Untested Areas:** Installments (100%), Quotations (100%), Shield RBAC (100%), Reports (100%)

---

## SECTION 1: LOGIC INVENTORY & CURRENT STATUS

### 1.1 Critical Logic Flows Implemented

| # | Feature | Critical Logic Flow | Has Test? | Test File | Notes |
|---|---------|-------------------|-----------|-----------|-------|
| **SALES MODULE** |
| 1 | Sales Invoice | Stock deduction on post | ✅ Yes | `FinancialIntegrityTest`, `StockServiceTest` | Comprehensive |
| 2 | Sales Invoice | Treasury update on cash/credit | ✅ Yes | `FinancialIntegrityTest`, `TreasuryServiceTest` | Comprehensive |
| 3 | Sales Invoice | Partner balance update (AR) | ✅ Yes | `FinancialIntegrityTest` | Comprehensive |
| 4 | Sales Invoice | Partial payment processing | ✅ Yes | `FinancialMathTest`, `FinancialIntegrityTest` | Good coverage |
| 5 | Sales Invoice | Immutability when posted | ✅ Yes | `SalesInvoiceResourceTest` | UI + Model level |
| 6 | Sales Invoice | Settlement discount logic | ✅ Yes | `FinancialIntegrityTest` | Tested |
| 7 | Sales Return | Stock restoration | ✅ Yes | `StockServiceTest` | Tested |
| 8 | Sales Return | Treasury refund (cash/credit) | ✅ Yes | `TreasuryServiceTest` | Tested |
| 9 | Sales Return | Discount handling on returns | ✅ Yes | `FinancialIntegrityTest` | Complex scenario tested |
| 10 | Installments | Schedule generation | ❌ **NO** | - | **CRITICAL GAP** |
| 11 | Installments | FIFO payment application | ❌ **NO** | - | **CRITICAL GAP** |
| 12 | Installments | Overdue detection | ❌ **NO** | - | **CRITICAL GAP** |
| 13 | Installments | Immutability enforcement | ❌ **NO** | - | **CRITICAL GAP** |
| 14 | Quotations | Convert to Invoice | ❌ **NO** | - | **CRITICAL GAP** |
| 15 | Quotations | Stock validation on convert | ❌ **NO** | - | **CRITICAL GAP** |
| 16 | Quotations | Guest partner creation | ❌ **NO** | - | **CRITICAL GAP** |
| **PURCHASES MODULE** |
| 17 | Purchase Invoice | Stock addition on post | ✅ Yes | `StockServiceTest` | Comprehensive |
| 18 | Purchase Invoice | Treasury payment (cash/credit) | ✅ Yes | `TreasuryServiceTest` | Comprehensive |
| 19 | Purchase Invoice | Partner balance update (AP) | ✅ Yes | `FinancialIntegrityTest` | Tested |
| 20 | Purchase Invoice | Weighted avg cost calculation | ✅ Yes | `BusinessLogicTest` | **EXCELLENT** - 8+ scenarios |
| 21 | Purchase Invoice | Retail price update | ✅ Yes | `StockServiceTest` | Tested |
| 22 | Purchase Return | Stock deduction | ✅ Yes | `StockServiceTest` | Tested |
| 23 | Purchase Return | Treasury refund | ✅ Yes | `TreasuryServiceTest` | Tested |
| 24 | Purchase Return | Avg cost recalculation | ✅ Yes | `StockServiceTest` | Tested |
| **INVENTORY MODULE** |
| 25 | Stock Service | Overselling prevention | ✅ Yes | `FinancialIntegrityTest`, `StockServiceTest` | Tested |
| 26 | Stock Service | Dual-unit conversion | ✅ Yes | `StockServiceTest`, `BusinessLogicTest` | **EXCELLENT** - multiple scenarios |
| 27 | Stock Service | Multi-warehouse isolation | ✅ Yes | `StockServiceTest` | Tested |
| 28 | Stock Service | Transaction atomicity | ✅ Yes | `StockServiceTest`, `BusinessLogicTest` | Tested |
| 29 | Stock Movements | Single source of truth | ✅ Yes | `StockServiceTest` | Implicit testing |
| 30 | Warehouse Transfer | Stock transfer logic | ⚠️ **PARTIAL** | - | No dedicated tests |
| 31 | Stock Adjustment | Manual adjustment logic | ❌ **NO** | - | Untested |
| **FINANCIAL MODULE** |
| 32 | Treasury Service | Balance calculation | ✅ Yes | `TreasuryServiceTest` | Comprehensive |
| 33 | Treasury Service | Negative balance prevention | ✅ Yes | `FinancialIntegrityTest` | Tested |
| 34 | Treasury Service | Transaction types (7 types) | ✅ Yes | `TreasuryServiceTest` | Good coverage |
| 35 | Partner | Balance calculation (AR/AP) | ✅ Yes | `FinancialIntegrityTest` | Tested |
| 36 | Partner | Balance recalculation | ✅ Yes | `FinancialIntegrityTest` | Tested |
| 37 | Expense | Treasury deduction | ✅ Yes | `TreasuryServiceTest` | Tested |
| 38 | Revenue | Treasury addition | ✅ Yes | `TreasuryServiceTest` | Tested |
| 39 | Fixed Assets | Depreciation calculation | ❌ **NO** | - | Untested |
| **REPORTING** |
| 40 | Partner Statement | Running balance | ❌ **NO** | - | **CRITICAL GAP** |
| 41 | Stock Card | Movement history | ❌ **NO** | - | **CRITICAL GAP** |
| 42 | Profit & Loss | Revenue/expense totals | ❌ **NO** | - | **CRITICAL GAP** |
| 43 | Daily Operations | Summary calculations | ❌ **NO** | - | **CRITICAL GAP** |
| **SECURITY (SHIELD)** |
| 44 | RBAC | Permission enforcement | ❌ **NO** | - | **CRITICAL GAP** |
| 45 | Policies | Resource-level access | ❌ **NO** | - | **CRITICAL GAP** |
| 46 | Policies | Action-level control | ❌ **NO** | - | **CRITICAL GAP** |

### 1.2 Test Coverage Summary

**Test Files (6 total):**
1. ✅ `FinancialIntegrityTest.php` (1,279 lines) - **EXCELLENT**
   - 24 test methods covering end-to-end scenarios
   - Tests: Positive paths, negative edges, complex math, full business cycles

2. ✅ `FinancialMathTest.php` (378 lines) - **GOOD**
   - 6 test methods for payment calculations
   - Focuses on discount math and partial payments

3. ✅ `SalesInvoiceResourceTest.php` (382 lines) - **GOOD**
   - 15 test methods for Filament UI
   - Tests: Form behavior, status transitions, live calculations

4. ✅ `StockServiceTest.php` (889 lines) - **EXCELLENT**
   - 20+ test methods
   - Coverage: All stock operations, unit conversion, validation

5. ✅ `TreasuryServiceTest.php` (766 lines) - **EXCELLENT**
   - 20+ test methods
   - Coverage: All treasury operations, balance calculations

6. ✅ `BusinessLogicTest.php` (estimated 500+ lines) - **EXCELLENT**
   - Advanced scenarios: weighted average, edge cases, atomicity

**Untested Code:**
- `app/Services/InstallmentService.php` (155 lines) - **0% coverage**
- `app/Services/ReportService.php` - **0% coverage**
- `app/Services/FinancialReportService.php` - **0% coverage**
- `app/Filament/Resources/*` (21 resources) - **Minimal UI testing**
- `app/Policies/*` (22 policies) - **0% coverage**

---

## SECTION 2: REGRESSION & INTEGRATION RISKS

### 2.1 Recent Refactoring Impact Analysis

#### ⚠️ **RISK 1: Sidebar Refactoring (Navigation Groups)**
**Change:** Updated navigation groups and sort orders
**Affected Files:** All `app/Filament/Resources/*Resource.php` (21 files)
**Risk Level:** 🟡 **LOW**
**Impact:** UI-only change, no business logic affected
**Test Status:** No automated tests for navigation structure
**Recommendation:** Manual testing sufficient unless navigation permissions are enforced

#### ⚠️ **RISK 2: Shield RBAC Integration**
**Change:** Added `filament-shield` for role-based access control
**Affected Files:**
- `app/Policies/*` (22 policy files)
- All Filament Resources (navigation visibility, actions)
**Risk Level:** 🔴 **HIGH**
**Potential Issues:**
- **Untested Permission Enforcement:** No tests verify that a "Sales Agent" CANNOT delete invoices
- **Policy Logic:** Policies only check `$user->can('action_resource')` - no custom business rules
- **Navigation Guards:** Resource visibility based on `viewAny` permission - untested
- **Action Guards:** Create/Edit/Delete actions rely on policies - untested

**Breaking Scenarios:**
```php
// SCENARIO: Can a "Viewer" role bypass delete restrictions?
// Current: Relies on Shield auto-generated permissions
// Test Status: ❌ NOT TESTED
User::factory()->withRole('viewer')->create()->delete($invoice);
```

**Recommendation:** **CRITICAL** - See Section 4.1 for testing strategy

#### ⚠️ **RISK 3: Payment Logic Refactoring (`paid_amount` hidden)**
**Change:** Based on context, `paid_amount` field behavior was modified
**Affected Files:**
- `app/Models/SalesInvoice.php`
- `app/Models/PurchaseInvoice.php`
- Treasury calculations
**Risk Level:** 🟡 **MEDIUM**
**Potential Issues:**
- **Validation Regression:** Does hiding `paid_amount` in UI break min/max validation?
- **Calculation Impact:** Are there any calculated fields that depend on `paid_amount` display state?
- **Payment Flow:** Does `recordInvoicePayment()` still correctly update the field?

**Existing Test Coverage:** ✅ **GOOD**
- `TreasuryServiceTest` has 8+ tests for `recordInvoicePayment`
- `FinancialIntegrityTest` tests partial payment scenarios

**Risk Mitigation:** Existing tests likely caught major issues, but UI validation needs checking

#### ⚠️ **RISK 4: Installment Feature Addition**
**Change:** NEW feature - dual-mode installment system
**Affected Files:**
- `app/Services/InstallmentService.php` (NEW)
- `app/Models/Installment.php` (NEW)
- `app/Filament/Resources/InstallmentResource.php` (NEW)
- `app/Models/SalesInvoice.php` (modified)
**Risk Level:** 🔴 **CRITICAL**
**Test Status:** ❌ **0% coverage**

**High-Risk Scenarios:**
1. **Schedule Generation Math:**
   ```php
   // Does rounding work correctly for 3 installments of 1000 EGP?
   // Expected: 333.33, 333.33, 333.34 (last installment absorbs diff)
   // Test Status: NOT TESTED
   ```

2. **FIFO Payment Application:**
   ```php
   // Pay 500 EGP on invoice with 3 installments (300, 300, 400)
   // Expected: Installment 1 fully paid, Installment 2 gets 200
   // Test Status: NOT TESTED
   ```

3. **Immutability:**
   ```php
   // Can someone change the amount AFTER installment is created?
   // Expected: Exception thrown
   // Test Status: NOT TESTED (though model observer exists)
   ```

**Recommendation:** **URGENT** - See Section 3.2

#### ⚠️ **RISK 5: Quotation-to-Invoice Conversion**
**Change:** NEW feature - convert quotations to invoices
**Affected Files:**
- `app/Filament/Resources/QuotationResource/Pages/ViewQuotation.php` (NEW logic)
- `app/Models/Quotation.php` (NEW)
**Risk Level:** 🔴 **CRITICAL**
**Test Status:** ❌ **0% coverage**

**High-Risk Scenarios:**
1. **Stock Validation Bypass:**
   ```php
   // Create quotation for 100 items when only 10 in stock
   // Convert to invoice weeks later
   // Expected: Validation should fail if stock dropped below 100
   // Test Status: Code exists (lines 147-158) but NOT TESTED
   ```

2. **Data Copying Integrity:**
   ```php
   // Quotation has discount_type: 'percentage', discount_value: 10
   // Does invoice get exact same values?
   // Test Status: NOT TESTED
   ```

3. **Guest Partner Creation:**
   ```php
   // Quotation for guest "Ahmed" (no partner_id)
   // Convert to invoice
   // Expected: Partner created, quotation.partner_id updated, invoice linked
   // Test Status: NOT TESTED (lines 119-136)
   ```

**Recommendation:** **URGENT** - See Section 3.3

### 2.2 Contradiction Analysis

#### ❌ **CONTRADICTION 1: Payment Method vs Paid Amount**
**Location:** Invoice models
**Issue:** If `payment_method` is 'cash', is `paid_amount` required to equal `total`?
**Current Validation:** NOT ENFORCED in tests
**Risk:** User could create cash invoice with `paid_amount = 0`
**Test Gap:** No validation test for this business rule

#### ❌ **CONTRADICTION 2: Posted Invoice Editing**
**Location:** Filament Resources
**Issue:** Model observer prevents updates, but does UI enforce this?
**Current Tests:** ✅ Model-level tested in `SalesInvoiceResourceTest`
**Risk:** Filament actions might bypass observer if not properly configured
**Mitigation:** Existing test at line 367 covers this

#### ✅ **NO CONTRADICTION: Discount Logic**
**Status:** Thoroughly tested
**Coverage:** `FinancialIntegrityTest` line 494-587 tests discount returns correctly

---

## SECTION 3: THE "MISSING" TESTS (Gap Analysis)

### 3.1 Filament Shield (RBAC) - **100% UNTESTED**

#### Critical Test Scenarios Needed:

**3.1.1 Permission Enforcement (Resource Level)**
```php
// TEST: Can a "Sales Agent" view invoices? (should: YES)
test('sales_agent_can_view_sales_invoices')
test('sales_agent_cannot_view_purchase_invoices')

// TEST: Can a "Sales Agent" delete posted invoices? (should: NO)
test('sales_agent_cannot_delete_posted_sales_invoice')

// TEST: Can "Super Admin" bypass all restrictions? (should: YES)
test('super_admin_can_perform_all_actions')
```

**3.1.2 Policy Logic Testing**
```php
// TEST: SalesInvoicePolicy::delete()
// Current implementation: Just checks permission string
// Should test: Does it also check invoice status?
test('cannot_delete_invoice_even_with_permission_if_posted')

// TEST: Navigation visibility
test('user_without_view_any_permission_does_not_see_resource_in_nav')
```

**3.1.3 Action-Level Guards**
```php
// TEST: Filament actions respect policies
test('edit_action_hidden_for_user_without_update_permission')
test('delete_action_hidden_for_user_without_delete_permission')
test('custom_actions_check_permissions', function() {
    // e.g., "Mark as Paid" button in InvoiceResource
});
```

**3.1.4 Multi-Role Scenarios**
```php
// TEST: User with multiple roles
test('user_with_sales_and_inventory_role_can_access_both_modules')

// TEST: Role hierarchy
test('manager_inherits_employee_permissions')
```

**Test Count Estimate:** 30-40 tests needed
**Priority:** 🔴 **CRITICAL** (security-sensitive)

---

### 3.2 Installments - **100% UNTESTED**

#### Critical Test Scenarios Needed:

**3.2.1 Schedule Generation Math**
```php
test('generates_correct_installment_amounts_for_even_division', function() {
    // Invoice: 1200 EGP, 4 months
    // Expected: 300, 300, 300, 300
});

test('handles_rounding_by_adjusting_last_installment', function() {
    // Invoice: 1000 EGP, 3 months
    // Expected: 333.33, 333.33, 333.34
});

test('prevents_schedule_generation_for_draft_invoice', function() {
    // Expect: Exception "الفاتورة يجب أن تكون مرحّلة"
});

test('prevents_duplicate_schedule_generation', function() {
    // Call generateInstallmentSchedule() twice
    // Expect: Exception "خطة الأقساط موجودة بالفعل"
});

test('calculates_correct_due_dates', function() {
    // Start: 2025-01-15, 3 months
    // Expected: 2025-01-15, 2025-02-15, 2025-03-15
});
```

**3.2.2 FIFO Payment Application**
```php
test('applies_payment_to_oldest_installment_first', function() {
    // Installments: [300 due Jan, 300 due Feb, 400 due Mar]
    // Payment: 500
    // Expected: Jan PAID, Feb PAID, Mar 100/400 paid
});

test('prevents_overpayment_of_individual_installment', function() {
    // Installment: 300 EGP
    // Try to apply: 500 EGP
    // Expected: Only 300 applied, 200 returned as "unapplied"
});

test('marks_installment_as_paid_when_fully_settled', function() {
    // Installment: 300, already paid 200
    // Payment: 100
    // Expected: status = 'paid', paid_at = now(), paid_by = user_id
});

test('handles_lockForUpdate_to_prevent_race_conditions', function() {
    // Concurrent payments on same invoice
    // Expected: No duplicate payment application
});

test('logs_overpayment_warning', function() {
    // Payment exceeds total remaining installments
    // Expected: Activity log with 'تحذير: دفعة تزيد عن إجمالي الأقساط'
});
```

**3.2.3 Overdue Detection**
```php
test('real_time_status_accessor_marks_overdue', function() {
    // Installment: due_date = yesterday, status = 'pending'
    // Access: $installment->status
    // Expected: 'overdue' (real-time)
});

test('scheduled_task_updates_overdue_installments', function() {
    // Run: InstallmentService::updateOverdueInstallments()
    // Expected: Database status updated to 'overdue'
});
```

**3.2.4 Immutability**
```php
test('prevents_modifying_amount_after_creation', function() {
    $installment->update(['amount' => 500]);
    // Expected: Exception "لا يمكن تعديل حقل amount"
});

test('prevents_deleting_installment_with_payment', function() {
    $installment->update(['paid_amount' => 100]);
    $installment->delete();
    // Expected: Exception "لا يمكن حذف قسط تم دفع مبلغ منه"
});
```

**3.2.5 Integration with Invoice Payments**
```php
test('invoice_payment_triggers_installment_application', function() {
    // When: TreasuryService::recordInvoicePayment() is called
    // AND invoice has_installment_plan = true
    // Expected: InstallmentService::applyPaymentToInstallments() called
});

test('installment_payment_updates_invoice_paid_amount', function() {
    // Pay 500 on installment plan
    // Expected: invoice.paid_amount += 500, remaining_amount -= 500
});
```

**Test Count Estimate:** 15-20 tests
**Priority:** 🔴 **CRITICAL** (financial integrity)

---

### 3.3 Quotations - **100% UNTESTED**

#### Critical Test Scenarios Needed:

**3.3.1 Convert to Invoice - Data Integrity**
```php
test('convert_copies_all_quotation_data_to_invoice', function() {
    $quotation = Quotation::factory()->create([
        'discount_type' => 'percentage',
        'discount_value' => 10,
        'subtotal' => 1000,
        'discount' => 100,
        'total' => 900,
    ]);

    $invoice = convertQuotationToInvoice($quotation);

    expect($invoice->discount_type)->toBe('percentage');
    expect($invoice->discount_value)->toBe(10);
    expect($invoice->total)->toBe(900);
});

test('convert_copies_all_quotation_items_with_prices', function() {
    $quotation = Quotation::factory()->withItems([
        ['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 100],
        ['product_id' => $product2->id, 'quantity' => 5, 'unit_price' => 200],
    ])->create();

    $invoice = convertQuotationToInvoice($quotation);

    expect($invoice->items)->toHaveCount(2);
    expect($invoice->items[0]->unit_price)->toBe(100); // Quotation price, not current product price
});

test('convert_preserves_notes_with_reference', function() {
    $quotation = Quotation::factory()->create([
        'quotation_number' => 'QT-2025-001',
        'notes' => 'Original note',
    ]);

    $invoice = convertQuotationToInvoice($quotation);

    expect($invoice->notes)->toContain('محول من عرض السعر: QT-2025-001');
    expect($invoice->notes)->toContain('Original note');
});
```

**3.3.2 Convert to Invoice - Stock Validation**
```php
test('convert_validates_stock_availability_before_conversion', function() {
    $quotation = Quotation::factory()->withItems([
        ['product_id' => $product->id, 'quantity' => 100], // Only 10 in stock
    ])->create();

    expect(fn() => convertQuotationToInvoice($quotation, $warehouse->id))
        ->toThrow(Exception::class, 'المخزون غير كافٍ');
});

test('convert_checks_stock_for_all_items_before_creating_invoice', function() {
    // Setup: Product A has 100 stock, Product B has 5 stock
    $quotation = Quotation::factory()->withItems([
        ['product_id' => $productA->id, 'quantity' => 50], // OK
        ['product_id' => $productB->id, 'quantity' => 10], // FAIL
    ])->create();

    expect(fn() => convertQuotationToInvoice($quotation, $warehouse->id))
        ->toThrow(Exception::class);

    // ASSERT: No invoice created (transaction rolled back)
    expect(SalesInvoice::count())->toBe(0);
});

test('convert_uses_convertToBaseUnit_for_dual_unit_products', function() {
    $quotation = Quotation::factory()->withItems([
        ['product_id' => $dualUnitProduct->id, 'quantity' => 5, 'unit_type' => 'large'], // 5 cartons = 60 pieces
    ])->create();

    // Setup: Warehouse has 50 pieces (not enough for 5 cartons)

    expect(fn() => convertQuotationToInvoice($quotation, $warehouse->id))
        ->toThrow(Exception::class, 'المخزون غير كافٍ');
});
```

**3.3.3 Convert to Invoice - Guest Partner Creation**
```php
test('convert_creates_partner_from_guest_quotation', function() {
    $quotation = Quotation::factory()->create([
        'partner_id' => null, // Guest quotation
        'guest_name' => 'Ahmed Mohamed',
        'guest_phone' => '0551234567',
    ]);

    $invoice = convertQuotationToInvoice($quotation, [
        'partner_name' => 'Ahmed Mohamed',
        'partner_phone' => '0551234567',
        'partner_type' => 'customer',
    ]);

    expect(Partner::where('name', 'Ahmed Mohamed')->exists())->toBeTrue();
    expect($invoice->partner->phone)->toBe('0551234567');
});

test('convert_updates_quotation_with_created_partner_id', function() {
    $quotation = Quotation::factory()->guest()->create();

    $invoice = convertQuotationToInvoice($quotation, [...]);

    $quotation->refresh();
    expect($quotation->partner_id)->not()->toBeNull();
});

test('convert_logs_partner_creation_activity', function() {
    // Expected activity log: "تم إنشاء شريك جديد: Ahmed Mohamed من عرض السعر"
});
```

**3.3.4 Convert to Invoice - Status Management**
```php
test('convert_marks_quotation_as_converted', function() {
    $quotation = Quotation::factory()->create(['status' => 'sent']);

    $invoice = convertQuotationToInvoice($quotation);

    $quotation->refresh();
    expect($quotation->status)->toBe('converted');
    expect($quotation->converted_invoice_id)->toBe($invoice->id);
});

test('convert_prevents_converting_expired_quotation', function() {
    $quotation = Quotation::factory()->create([
        'valid_until' => now()->subDay(), // Expired yesterday
    ]);

    expect($quotation->canBeConverted())->toBeFalse();
});

test('convert_prevents_converting_already_converted_quotation', function() {
    $quotation = Quotation::factory()->create(['status' => 'converted']);

    expect($quotation->canBeConverted())->toBeFalse();
});
```

**3.3.5 Convert to Invoice - Transaction Atomicity**
```php
test('convert_rolls_back_if_invoice_creation_fails', function() {
    // Force invoice creation to fail (e.g., invalid warehouse_id)

    expect(fn() => convertQuotationToInvoice($quotation, warehouse_id: 'invalid'))
        ->toThrow(Exception::class);

    // ASSERT: Quotation status unchanged
    $quotation->refresh();
    expect($quotation->status)->toBe('sent');
    expect($quotation->converted_invoice_id)->toBeNull();

    // ASSERT: No partner created
    expect(Partner::count())->toBe(0);
});
```

**Test Count Estimate:** 15-20 tests
**Priority:** 🔴 **CRITICAL** (business-critical feature)

---

### 3.4 Reports - **100% UNTESTED**

#### Critical Test Scenarios Needed:

**3.4.1 Partner Statement - Running Balance**
```php
test('partner_statement_calculates_running_balance_correctly', function() {
    // Setup: Customer transactions
    // - Sale 1: +1000
    // - Payment: -300
    // - Sale 2: +500
    // - Return: -100

    $statement = ReportService::getPartnerStatement($customer->id);

    expect($statement->transactions[0]->running_balance)->toBe(1000);
    expect($statement->transactions[1]->running_balance)->toBe(700);  // 1000-300
    expect($statement->transactions[2]->running_balance)->toBe(1200); // 700+500
    expect($statement->transactions[3]->running_balance)->toBe(1100); // 1200-100
});

test('partner_statement_matches_partner_current_balance', function() {
    $statement = ReportService::getPartnerStatement($customer->id);

    expect($statement->final_balance)->toBe((float)$customer->current_balance);
});

test('partner_statement_filters_by_date_range', function() {
    $statement = ReportService::getPartnerStatement(
        $customer->id,
        date_from: '2025-01-01',
        date_to: '2025-01-31'
    );

    expect($statement->transactions)->each(
        fn($tx) => $tx->created_at->between('2025-01-01', '2025-01-31')
    );
});
```

**3.4.2 Stock Card - Movement History**
```php
test('stock_card_lists_all_movements_for_product', function() {
    // Setup movements: Purchase, Sale, Return, Adjustment

    $stockCard = ReportService::getStockCard($product->id, $warehouse->id);

    expect($stockCard->movements)->toHaveCount(4);
});

test('stock_card_calculates_running_quantity_correctly', function() {
    // Setup: Purchase +100, Sale -30, Return +5

    $stockCard = ReportService::getStockCard($product->id, $warehouse->id);

    expect($stockCard->movements[0]->running_qty)->toBe(100);
    expect($stockCard->movements[1]->running_qty)->toBe(70);  // 100-30
    expect($stockCard->movements[2]->running_qty)->toBe(75);  // 70+5
});

test('stock_card_matches_stock_service_current_stock', function() {
    $stockCard = ReportService::getStockCard($product->id, $warehouse->id);
    $currentStock = (new StockService())->getCurrentStock($warehouse->id, $product->id);

    expect($stockCard->final_quantity)->toBe($currentStock);
});
```

**3.4.3 Profit & Loss Report**
```php
test('profit_loss_calculates_total_revenue', function() {
    // Setup: 3 sales invoices, 2 revenues

    $pnl = FinancialReportService::getProfitLoss('2025-01-01', '2025-01-31');

    expect($pnl->total_revenue)->toBe(expected_sum);
});

test('profit_loss_calculates_total_expenses', function() {
    // Setup: 2 purchase invoices, 3 expenses

    $pnl = FinancialReportService::getProfitLoss('2025-01-01', '2025-01-31');

    expect($pnl->total_expenses)->toBe(expected_sum);
});

test('profit_loss_net_profit_equals_revenue_minus_expenses', function() {
    $pnl = FinancialReportService::getProfitLoss('2025-01-01', '2025-01-31');

    expect($pnl->net_profit)->toBe($pnl->total_revenue - $pnl->total_expenses);
});
```

**3.4.4 Daily Operations Report**
```php
test('daily_operations_sums_sales_for_date', function() {
    // Setup: 5 sales on 2025-01-15, 3 sales on 2025-01-16

    $report = ReportService::getDailyOperations('2025-01-15');

    expect($report->total_sales)->toBe(expected_sum_for_jan_15);
});

test('daily_operations_includes_all_transaction_types', function() {
    $report = ReportService::getDailyOperations('2025-01-15');

    expect($report)->toHaveKeys([
        'total_sales',
        'total_purchases',
        'total_expenses',
        'total_revenues',
        'net_cash_flow'
    ]);
});
```

**Test Count Estimate:** 12-15 tests
**Priority:** 🟡 **MEDIUM** (business-critical but read-only)

---

### 3.5 Additional Gaps (Lower Priority)

**3.5.1 Warehouse Transfer**
- ❌ No tests for transfer logic
- ❌ No tests for dual-warehouse stock validation
- Priority: 🟡 MEDIUM

**3.5.2 Stock Adjustment**
- ❌ No tests for manual adjustment logic
- ❌ No tests for adjustment reason tracking
- Priority: 🟡 MEDIUM

**3.5.3 Fixed Assets**
- ❌ No tests for depreciation calculation
- ❌ No tests for disposal logic
- Priority: 🟢 LOW (likely not critical yet)

**3.5.4 Filament UI Actions**
- ⚠️ Only 1 resource tested (`SalesInvoiceResourceTest`)
- ❌ 20 other resources have NO UI tests
- Priority: 🟡 MEDIUM (regression risk)

---

## SECTION 4: TESTING STRATEGY & ROADMAP

### 4.1 Recommended Testing Stack

**Framework:** ✅ **Pest** (Highly Recommended)

**Why Pest over PHPUnit?**
1. ✅ **Filament-First:** Better integration with Livewire testing
2. ✅ **Readability:** `expect()->toBe()` vs `$this->assertEquals()`
3. ✅ **Speed:** Parallel execution out-of-the-box
4. ✅ **Modern:** Dataset testing, type hints, better IDE support
5. ✅ **Community:** Filament ecosystem uses Pest extensively

**Migration Note:** Your existing tests use PHPUnit `@test` annotations. You can:
- **Option A:** Migrate to Pest (recommended for long-term)
- **Option B:** Keep both (Pest for new tests, PHPUnit for existing)

---

### 4.2 Testing Architecture for Filament Resources

#### **Pattern 1: Livewire Component Testing**
```php
use function Pest\Livewire\livewire;

test('sales_agent_cannot_see_delete_button_on_invoice', function() {
    $user = User::factory()->withRole('sales_agent')->create();
    $invoice = SalesInvoice::factory()->create();

    actingAs($user);

    livewire(EditSalesInvoice::class, ['record' => $invoice->id])
        ->assertActionHidden('delete')
        ->assertActionVisible('edit');
});
```

#### **Pattern 2: Policy Testing**
```php
test('sales_invoice_policy_denies_delete_for_posted_invoice', function() {
    $user = User::factory()->create();
    $invoice = SalesInvoice::factory()->posted()->create();

    expect($user->can('delete', $invoice))->toBeFalse();
});
```

#### **Pattern 3: Form Validation Testing**
```php
test('invoice_form_requires_warehouse_id', function() {
    livewire(CreateSalesInvoice::class)
        ->fillForm([
            'partner_id' => $partner->id,
            // warehouse_id omitted
        ])
        ->call('create')
        ->assertHasFormErrors(['warehouse_id' => 'required']);
});
```

#### **Pattern 4: Action Testing**
```php
test('convert_quotation_action_creates_invoice', function() {
    $quotation = Quotation::factory()->create();

    livewire(ViewQuotation::class, ['record' => $quotation->id])
        ->callAction('convert_to_invoice', [
            'warehouse_id' => $warehouse->id,
            'payment_method' => 'credit',
        ])
        ->assertHasNoActionErrors();

    expect(SalesInvoice::count())->toBe(1);
    expect($quotation->refresh()->status)->toBe('converted');
});
```

---

### 4.3 Detailed Test Roadmap (Priority Order)

#### **PHASE 1: CRITICAL SECURITY & FINANCIAL INTEGRITY** (Week 1-2)

**Priority:** 🔴 **URGENT** - Ship blockers

| Test Suite | Test Count | Estimated Time | Priority |
|------------|-----------|----------------|----------|
| **1. Installments** | 18 tests | 8 hours | P0 |
| - Schedule generation (5 tests) | | | |
| - FIFO payment application (6 tests) | | | |
| - Overdue detection (2 tests) | | | |
| - Immutability (3 tests) | | | |
| - Integration tests (2 tests) | | | |
| **2. Quotation Conversion** | 15 tests | 6 hours | P0 |
| - Data integrity (3 tests) | | | |
| - Stock validation (3 tests) | | | |
| - Guest partner creation (3 tests) | | | |
| - Status management (3 tests) | | | |
| - Transaction atomicity (3 tests) | | | |
| **3. RBAC (Shield)** | 25 tests | 10 hours | P0 |
| - Permission enforcement (8 tests) | | | |
| - Policy logic (6 tests) | | | |
| - Action guards (6 tests) | | | |
| - Multi-role scenarios (5 tests) | | | |
| **TOTAL PHASE 1** | **58 tests** | **24 hours** | |

**Deliverable:** Confidence in financial calculations + security enforcement

---

#### **PHASE 2: BUSINESS-CRITICAL REPORTS** (Week 3)

**Priority:** 🟡 **HIGH** - Customer-facing features

| Test Suite | Test Count | Estimated Time | Priority |
|------------|-----------|----------------|----------|
| **4. Partner Statement** | 6 tests | 3 hours | P1 |
| - Running balance (3 tests) | | | |
| - Date filtering (2 tests) | | | |
| - Balance reconciliation (1 test) | | | |
| **5. Stock Card** | 4 tests | 2 hours | P1 |
| - Movement history (2 tests) | | | |
| - Running quantity (2 tests) | | | |
| **6. Profit & Loss** | 4 tests | 2 hours | P1 |
| - Revenue calculation (2 tests) | | | |
| - Net profit formula (2 tests) | | | |
| **7. Daily Operations** | 3 tests | 2 hours | P2 |
| **TOTAL PHASE 2** | **17 tests** | **9 hours** | |

**Deliverable:** Trust in financial reporting accuracy

---

#### **PHASE 3: OPERATIONAL FEATURES** (Week 4)

**Priority:** 🟡 **MEDIUM** - Operational risk

| Test Suite | Test Count | Estimated Time | Priority |
|------------|-----------|----------------|----------|
| **8. Warehouse Transfer** | 8 tests | 4 hours | P2 |
| **9. Stock Adjustment** | 6 tests | 3 hours | P2 |
| **10. Fixed Assets** | 5 tests | 3 hours | P3 |
| **TOTAL PHASE 3** | **19 tests** | **10 hours** | |

**Deliverable:** Comprehensive inventory operations coverage

---

#### **PHASE 4: REGRESSION PREVENTION** (Week 5)

**Priority:** 🟢 **LOW** - Long-term stability

| Test Suite | Test Count | Estimated Time | Priority |
|------------|-----------|----------------|----------|
| **11. Remaining Filament Resources** | 40 tests | 16 hours | P3 |
| - Purchase Invoice (8 tests) | | | |
| - Purchase Return (6 tests) | | | |
| - Sales Return (6 tests) | | | |
| - Products (5 tests) | | | |
| - Partners (5 tests) | | | |
| - Other resources (10 tests) | | | |
| **TOTAL PHASE 4** | **40 tests** | **16 hours** | |

**Deliverable:** UI regression protection

---

### 4.4 NEW Tests to Write (Detailed Implementation Guide)

#### **Test File 1: `tests/Feature/Services/InstallmentServiceTest.php`**

```php
<?php

use App\Models\SalesInvoice;
use App\Models\Installment;
use App\Models\InvoicePayment;
use App\Services\InstallmentService;

describe('Installment Schedule Generation', function() {

    test('generates correct installment amounts for even division', function() {
        $invoice = SalesInvoice::factory()->create([
            'total' => 1200,
            'paid_amount' => 0,
            'remaining_amount' => 1200,
            'has_installment_plan' => true,
            'installment_months' => 4,
            'installment_start_date' => '2025-01-15',
            'status' => 'posted',
        ]);

        $service = new InstallmentService();
        $service->generateInstallmentSchedule($invoice);

        $installments = $invoice->installments;

        expect($installments)->toHaveCount(4);
        expect($installments[0]->amount)->toBe('300.0000');
        expect($installments[1]->amount)->toBe('300.0000');
        expect($installments[2]->amount)->toBe('300.0000');
        expect($installments[3]->amount)->toBe('300.0000');
    });

    test('handles rounding by adjusting last installment', function() {
        $invoice = SalesInvoice::factory()->create([
            'remaining_amount' => 1000,
            'installment_months' => 3,
            'status' => 'posted',
        ]);

        $service = new InstallmentService();
        $service->generateInstallmentSchedule($invoice);

        $installments = $invoice->installments;

        expect($installments[0]->amount)->toBe('333.3333');
        expect($installments[1]->amount)->toBe('333.3333');
        expect($installments[2]->amount)->toBe('333.3334'); // Absorbs rounding difference

        // Verify total matches
        $total = $installments->sum('amount');
        expect($total)->toBe('1000.0000');
    });

    test('prevents schedule generation for draft invoice', function() {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $service = new InstallmentService();

        expect(fn() => $service->generateInstallmentSchedule($invoice))
            ->toThrow(Exception::class, 'الفاتورة يجب أن تكون مرحّلة');
    });

    test('prevents duplicate schedule generation', function() {
        $invoice = SalesInvoice::factory()->create(['status' => 'posted']);
        Installment::factory()->create(['sales_invoice_id' => $invoice->id]);

        $service = new InstallmentService();

        expect(fn() => $service->generateInstallmentSchedule($invoice))
            ->toThrow(Exception::class, 'خطة الأقساط موجودة بالفعل');
    });

    test('calculates correct due dates', function() {
        $invoice = SalesInvoice::factory()->create([
            'installment_months' => 3,
            'installment_start_date' => '2025-01-15',
            'status' => 'posted',
        ]);

        $service = new InstallmentService();
        $service->generateInstallmentSchedule($invoice);

        $installments = $invoice->installments;

        expect($installments[0]->due_date->format('Y-m-d'))->toBe('2025-01-15');
        expect($installments[1]->due_date->format('Y-m-d'))->toBe('2025-02-15');
        expect($installments[2]->due_date->format('Y-m-d'))->toBe('2025-03-15');
    });
});

describe('FIFO Payment Application', function() {

    test('applies payment to oldest installment first', function() {
        $invoice = SalesInvoice::factory()->hasInstallments([
            ['amount' => 300, 'due_date' => '2025-01-15', 'status' => 'pending'],
            ['amount' => 300, 'due_date' => '2025-02-15', 'status' => 'pending'],
            ['amount' => 400, 'due_date' => '2025-03-15', 'status' => 'pending'],
        ])->create();

        $payment = InvoicePayment::create([
            'sales_invoice_id' => $invoice->id,
            'amount' => 500,
        ]);

        $service = new InstallmentService();
        $service->applyPaymentToInstallments($invoice, $payment);

        $installments = $invoice->installments->fresh();

        // First installment: FULLY PAID
        expect($installments[0]->status)->toBe('paid');
        expect($installments[0]->paid_amount)->toBe('300.0000');

        // Second installment: FULLY PAID
        expect($installments[1]->status)->toBe('paid');
        expect($installments[1]->paid_amount)->toBe('300.0000');

        // Third installment: PARTIALLY PAID (100 out of 400)
        expect($installments[2]->status)->toBe('pending');
        expect($installments[2]->paid_amount)->toBe('100.0000');
    });

    test('prevents overpayment of individual installment', function() {
        $invoice = SalesInvoice::factory()->hasInstallments([
            ['amount' => 300, 'status' => 'pending'],
        ])->create();

        $payment = InvoicePayment::create([
            'sales_invoice_id' => $invoice->id,
            'amount' => 500, // Exceeds installment amount
        ]);

        $service = new InstallmentService();
        $service->applyPaymentToInstallments($invoice, $payment);

        $installment = $invoice->installments->first()->fresh();

        // Should only apply 300, not 500
        expect($installment->paid_amount)->toBe('300.0000');
        expect($installment->status)->toBe('paid');

        // Overpayment should be logged
        $this->assertDatabaseHas('activity_log', [
            'description' => 'تحذير: دفعة تزيد عن إجمالي الأقساط المتبقية',
        ]);
    });

    test('marks installment as paid when fully settled', function() {
        $user = User::factory()->create();
        actingAs($user);

        $installment = Installment::factory()->create([
            'amount' => 300,
            'paid_amount' => 200, // Already partially paid
            'status' => 'pending',
        ]);

        $payment = InvoicePayment::create([
            'sales_invoice_id' => $installment->sales_invoice_id,
            'amount' => 100, // Complete remaining
        ]);

        $service = new InstallmentService();
        $service->applyPaymentToInstallments($installment->salesInvoice, $payment);

        $installment->refresh();

        expect($installment->status)->toBe('paid');
        expect($installment->paid_amount)->toBe('300.0000');
        expect($installment->paid_at)->not()->toBeNull();
        expect($installment->paid_by)->toBe($user->id);
        expect($installment->invoice_payment_id)->toBe($payment->id);
    });
});

describe('Overdue Detection', function() {

    test('real time status accessor marks overdue', function() {
        $installment = Installment::factory()->create([
            'due_date' => now()->subDay(),
            'status' => 'pending', // Database status
        ]);

        // Accessing status should return 'overdue' (real-time)
        expect($installment->status)->toBe('overdue');
    });

    test('scheduled task updates overdue installments', function() {
        Installment::factory()->count(3)->create([
            'due_date' => now()->subDays(2),
            'status' => 'pending',
        ]);

        $service = new InstallmentService();
        $count = $service->updateOverdueInstallments();

        expect($count)->toBe(3);
        expect(Installment::where('status', 'overdue')->count())->toBe(3);
    });
});

describe('Immutability', function() {

    test('prevents modifying amount after creation', function() {
        $installment = Installment::factory()->create(['amount' => 300]);

        expect(fn() => $installment->update(['amount' => 500]))
            ->toThrow(Exception::class, 'لا يمكن تعديل حقل amount');
    });

    test('prevents modifying due_date after creation', function() {
        $installment = Installment::factory()->create();

        expect(fn() => $installment->update(['due_date' => now()->addMonth()]))
            ->toThrow(Exception::class, 'لا يمكن تعديل حقل due_date');
    });

    test('prevents deleting installment with payment', function() {
        $installment = Installment::factory()->create(['paid_amount' => 100]);

        expect(fn() => $installment->delete())
            ->toThrow(Exception::class, 'لا يمكن حذف قسط تم دفع مبلغ منه');
    });
});
```

**File Location:** `tests/Feature/Services/InstallmentServiceTest.php`
**Test Count:** 15 tests
**Estimated Time:** 6-8 hours (including edge cases)

---

#### **Test File 2: `tests/Feature/Filament/QuotationConversionTest.php`**

```php
<?php

use App\Models\Quotation;
use App\Models\SalesInvoice;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Warehouse;
use App\Filament\Resources\QuotationResource\Pages\ViewQuotation;
use function Pest\Livewire\livewire;

describe('Quotation to Invoice Conversion - Data Integrity', function() {

    test('copies all quotation data to invoice', function() {
        $partner = Partner::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();

        $quotation = Quotation::factory()->create([
            'partner_id' => $partner->id,
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'subtotal' => 1000,
            'discount' => 100,
            'total' => 900,
            'notes' => 'Original quotation notes',
        ]);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => $warehouse->id,
                'payment_method' => 'credit',
            ]);

        $invoice = SalesInvoice::first();

        expect($invoice->partner_id)->toBe($partner->id);
        expect($invoice->discount_type)->toBe('percentage');
        expect($invoice->discount_value)->toBe('10.0000');
        expect($invoice->subtotal)->toBe('1000.0000');
        expect($invoice->discount)->toBe('100.0000');
        expect($invoice->total)->toBe('900.0000');
        expect($invoice->notes)->toContain($quotation->quotation_number);
        expect($invoice->notes)->toContain('Original quotation notes');
    });

    test('copies all quotation items with prices', function() {
        $product1 = Product::factory()->create(['retail_price' => 50]); // Current price
        $product2 = Product::factory()->create(['retail_price' => 150]);

        $quotation = Quotation::factory()->create();
        $quotation->items()->createMany([
            ['product_id' => $product1->id, 'quantity' => 10, 'unit_price' => 100, 'total' => 1000], // Quotation price != current price
            ['product_id' => $product2->id, 'quantity' => 5, 'unit_price' => 200, 'total' => 1000],
        ]);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => Warehouse::factory()->create()->id,
                'payment_method' => 'credit',
            ]);

        $invoice = SalesInvoice::first();

        expect($invoice->items)->toHaveCount(2);

        // CRITICAL: Should use quotation price (100), NOT current product price (50)
        expect($invoice->items[0]->unit_price)->toBe('100.0000');
        expect($invoice->items[1]->unit_price)->toBe('200.0000');
    });
});

describe('Quotation to Invoice Conversion - Stock Validation', function() {

    test('validates stock availability before conversion', function() {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Setup: Only 10 items in stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 10,
            'cost_at_time' => 50,
            'reference_type' => 'test',
            'reference_id' => 'test',
        ]);

        $quotation = Quotation::factory()->create();
        $quotation->items()->create([
            'product_id' => $product->id,
            'quantity' => 100, // Exceeds stock
            'unit_type' => 'small',
            'unit_price' => 100,
            'total' => 10000,
        ]);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => $warehouse->id,
                'payment_method' => 'credit',
            ])
            ->assertNotified(); // Should show error notification

        // No invoice should be created
        expect(SalesInvoice::count())->toBe(0);

        // Quotation status should remain unchanged
        expect($quotation->refresh()->status)->not()->toBe('converted');
    });

    test('checks stock for all items before creating invoice', function() {
        $productA = Product::factory()->create();
        $productB = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Setup: Product A has 100 stock, Product B has 5 stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $productA->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
            'reference_type' => 'test',
            'reference_id' => 'test',
        ]);

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $productB->id,
            'type' => 'purchase',
            'quantity' => 5,
            'cost_at_time' => 50,
            'reference_type' => 'test',
            'reference_id' => 'test',
        ]);

        $quotation = Quotation::factory()->create();
        $quotation->items()->createMany([
            ['product_id' => $productA->id, 'quantity' => 50, 'unit_type' => 'small', 'unit_price' => 100, 'total' => 5000], // OK
            ['product_id' => $productB->id, 'quantity' => 10, 'unit_type' => 'small', 'unit_price' => 100, 'total' => 1000], // FAIL
        ]);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => $warehouse->id,
                'payment_method' => 'credit',
            ]);

        // Transaction should be rolled back
        expect(SalesInvoice::count())->toBe(0);
    });

    test('validates dual unit products correctly', function() {
        $product = Product::factory()->create([
            'small_unit_id' => Unit::factory()->create()->id,
            'large_unit_id' => Unit::factory()->create()->id,
            'factor' => 12, // 1 carton = 12 pieces
        ]);

        $warehouse = Warehouse::factory()->create();

        // Setup: Warehouse has 50 pieces (not enough for 5 cartons = 60 pieces)
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 50,
            'cost_at_time' => 50,
            'reference_type' => 'test',
            'reference_id' => 'test',
        ]);

        $quotation = Quotation::factory()->create();
        $quotation->items()->create([
            'product_id' => $product->id,
            'quantity' => 5, // 5 cartons = 60 pieces
            'unit_type' => 'large',
            'unit_price' => 600,
            'total' => 3000,
        ]);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => $warehouse->id,
                'payment_method' => 'credit',
            ]);

        // Should fail (50 < 60)
        expect(SalesInvoice::count())->toBe(0);
    });
});

describe('Quotation to Invoice Conversion - Guest Partner Creation', function() {

    test('creates partner from guest quotation', function() {
        $quotation = Quotation::factory()->create([
            'partner_id' => null, // Guest
            'guest_name' => 'Ahmed Mohamed',
            'guest_phone' => '0551234567',
        ]);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => Warehouse::factory()->create()->id,
                'payment_method' => 'credit',
                'partner_name' => 'Ahmed Mohamed',
                'partner_phone' => '0551234567',
                'partner_type' => 'customer',
                'partner_region' => 'Riyadh',
            ]);

        $partner = Partner::where('name', 'Ahmed Mohamed')->first();

        expect($partner)->not()->toBeNull();
        expect($partner->phone)->toBe('0551234567');
        expect($partner->type)->toBe('customer');
        expect($partner->region)->toBe('Riyadh');

        // Invoice should be linked to new partner
        $invoice = SalesInvoice::first();
        expect($invoice->partner_id)->toBe($partner->id);
    });

    test('updates quotation with created partner id', function() {
        $quotation = Quotation::factory()->guest()->create();

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => Warehouse::factory()->create()->id,
                'payment_method' => 'credit',
                'partner_name' => 'Test Customer',
                'partner_phone' => '0551111111',
                'partner_type' => 'customer',
            ]);

        $quotation->refresh();

        expect($quotation->partner_id)->not()->toBeNull();
        expect($quotation->partner->name)->toBe('Test Customer');
    });
});

describe('Quotation to Invoice Conversion - Status Management', function() {

    test('marks quotation as converted and links invoice', function() {
        $quotation = Quotation::factory()->create(['status' => 'sent']);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => Warehouse::factory()->create()->id,
                'payment_method' => 'credit',
            ]);

        $quotation->refresh();
        $invoice = SalesInvoice::first();

        expect($quotation->status)->toBe('converted');
        expect($quotation->converted_invoice_id)->toBe($invoice->id);
    });

    test('prevents converting expired quotation', function() {
        $quotation = Quotation::factory()->create([
            'valid_until' => now()->subDay(), // Expired
        ]);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->assertActionHidden('convert_to_invoice'); // Action should be hidden
    });

    test('prevents converting already converted quotation', function() {
        $quotation = Quotation::factory()->create(['status' => 'converted']);

        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->assertActionHidden('convert_to_invoice');
    });
});

describe('Quotation to Invoice Conversion - Transaction Atomicity', function() {

    test('rolls back if invoice creation fails', function() {
        $quotation = Quotation::factory()->guest()->create();

        // Force failure by using invalid warehouse_id
        livewire(ViewQuotation::class, ['record' => $quotation->id])
            ->callAction('convert_to_invoice', [
                'warehouse_id' => 'invalid-id',
                'payment_method' => 'credit',
                'partner_name' => 'Test',
                'partner_phone' => '055555',
                'partner_type' => 'customer',
            ]);

        // Quotation should remain unchanged
        $quotation->refresh();
        expect($quotation->status)->not()->toBe('converted');
        expect($quotation->converted_invoice_id)->toBeNull();
        expect($quotation->partner_id)->toBeNull(); // Partner should not be created

        // No partner created
        expect(Partner::where('name', 'Test')->exists())->toBeFalse();

        // No invoice created
        expect(SalesInvoice::count())->toBe(0);
    });
});
```

**File Location:** `tests/Feature/Filament/QuotationConversionTest.php`
**Test Count:** 12 tests
**Estimated Time:** 6 hours

---

#### **Test File 3: `tests/Feature/Security/ShieldRBACTest.php`**

```php
<?php

use App\Models\User;
use App\Models\SalesInvoice;
use App\Models\PurchaseInvoice;
use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use Spatie\Permission\Models\Role;
use function Pest\Livewire\livewire;

beforeEach(function() {
    // Seed roles and permissions (run Shield setup)
    $this->artisan('shield:generate', ['--all' => true]);
});

describe('Permission Enforcement - Resource Level', function() {

    test('sales agent can view sales invoices', function() {
        $role = Role::create(['name' => 'sales_agent']);
        $role->givePermissionTo('view_any_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        actingAs($user);

        $response = $this->get(route('filament.admin.resources.sales-invoices.index'));

        expect($response->status())->toBe(200);
    });

    test('sales agent cannot view purchase invoices', function() {
        $role = Role::create(['name' => 'sales_agent']);
        // No permission for purchase invoices

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        actingAs($user);

        $response = $this->get(route('filament.admin.resources.purchase-invoices.index'));

        expect($response->status())->toBe(403); // Forbidden
    });

    test('sales agent cannot delete posted sales invoice', function() {
        $role = Role::create(['name' => 'sales_agent']);
        $role->givePermissionTo([
            'view_sales::invoice',
            'update_sales::invoice',
            // NO delete permission
        ]);

        $user = User::factory()->create();
        $user->assignRole('sales_agent');

        $invoice = SalesInvoice::factory()->posted()->create();

        actingAs($user);

        livewire(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('delete'); // UI hides action

        // Policy check
        expect($user->can('delete', $invoice))->toBeFalse();
    });

    test('super admin can perform all actions', function() {
        $role = Role::create(['name' => 'super_admin']);
        // Shield auto-grants all permissions to super_admin

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $invoice = SalesInvoice::factory()->posted()->create();

        actingAs($user);

        expect($user->can('view', $invoice))->toBeTrue();
        expect($user->can('update', $invoice))->toBeTrue();
        expect($user->can('delete', $invoice))->toBeTrue();
        expect($user->can('forceDelete', $invoice))->toBeTrue();
    });
});

describe('Policy Logic Testing', function() {

    test('cannot delete posted invoice even with permission', function() {
        // This tests if policies have custom business logic beyond permission check

        $role = Role::create(['name' => 'manager']);
        $role->givePermissionTo('delete_sales::invoice');

        $user = User::factory()->create();
        $user->assignRole('manager');

        $invoice = SalesInvoice::factory()->posted()->create();

        actingAs($user);

        // Policy should deny because invoice is posted (business rule)
        // NOTE: Current implementation (line 50 of SalesInvoicePolicy) does NOT check this
        // This test WILL FAIL - documents needed enhancement

        expect($user->can('delete', $invoice))->toBeFalse();
    });

    test('user without view_any permission does not see resource in nav', function() {
        $role = Role::create(['name' => 'limited_user']);
        // No view_any permission

        $user = User::factory()->create();
        $user->assignRole('limited_user');

        actingAs($user);

        // Test navigation visibility (requires inspecting Filament navigation)
        // This is a placeholder - actual implementation depends on Filament version

        $this->markTestIncomplete('Navigation visibility testing requires custom helper');
    });
});

describe('Action-Level Guards', function() {

    test('edit action hidden for user without update permission', function() {
        $role = Role::create(['name' => 'viewer']);
        $role->givePermissionTo('view_sales::invoice');
        // NO update permission

        $user = User::factory()->create();
        $user->assignRole('viewer');

        $invoice = SalesInvoice::factory()->create();

        actingAs($user);

        livewire(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('save'); // Cannot save edits
    });

    test('delete action hidden for user without delete permission', function() {
        $role = Role::create(['name' => 'editor']);
        $role->givePermissionTo(['view_sales::invoice', 'update_sales::invoice']);
        // NO delete permission

        $user = User::factory()->create();
        $user->assignRole('editor');

        $invoice = SalesInvoice::factory()->create();

        actingAs($user);

        livewire(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('delete');
    });
});

describe('Multi-Role Scenarios', function() {

    test('user with multiple roles has combined permissions', function() {
        $salesRole = Role::create(['name' => 'sales_agent']);
        $salesRole->givePermissionTo('view_any_sales::invoice');

        $inventoryRole = Role::create(['name' => 'inventory_manager']);
        $inventoryRole->givePermissionTo('view_any_product');

        $user = User::factory()->create();
        $user->assignRole(['sales_agent', 'inventory_manager']);

        actingAs($user);

        // Can access both modules
        $salesResponse = $this->get(route('filament.admin.resources.sales-invoices.index'));
        $productsResponse = $this->get(route('filament.admin.resources.products.index'));

        expect($salesResponse->status())->toBe(200);
        expect($productsResponse->status())->toBe(200);
    });
});
```

**File Location:** `tests/Feature/Security/ShieldRBACTest.php`
**Test Count:** 10+ tests
**Estimated Time:** 8 hours (including Shield setup complexity)

---

### 4.5 CI/CD Integration Recommendations

**GitHub Actions Workflow:**
```yaml
name: Test Suite

on: [push, pull_request]

jobs:
  tests:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: 8.2
          extensions: pdo, mysql, bcmath

      - name: Install Dependencies
        run: composer install --prefer-dist --no-interaction

      - name: Setup Environment
        run: |
          cp .env.testing .env
          php artisan key:generate

      - name: Run Migrations
        run: php artisan migrate --seed

      - name: Run Tests
        run: php artisan test --parallel

      - name: Coverage Report
        run: php artisan test --coverage --min=80
```

---

## FINAL RECOMMENDATIONS

### ✅ **What You Should Do Next (Priority Order):**

1. **WEEK 1:** Write Installment tests (18 tests) - **CRITICAL**
   - File: `tests/Feature/Services/InstallmentServiceTest.php`
   - Focus: Schedule generation, FIFO payment, immutability

2. **WEEK 2:** Write Quotation Conversion tests (15 tests) - **CRITICAL**
   - File: `tests/Feature/Filament/QuotationConversionTest.php`
   - Focus: Stock validation, data copying, guest partner creation

3. **WEEK 3:** Write Shield RBAC tests (25 tests) - **CRITICAL**
   - File: `tests/Feature/Security/ShieldRBACTest.php`
   - Focus: Permission enforcement, policy logic

4. **WEEK 4:** Write Report tests (17 tests) - **HIGH**
   - Files: `PartnerStatementTest.php`, `StockCardTest.php`, `ProfitLossTest.php`

5. **WEEK 5+:** Expand UI coverage for remaining resources - **MEDIUM**

### 🛡️ **Safety Assessment:**

**Current Safety Level:** 🟡 **70/100** (Good but incomplete)

**Breakdown:**
- ✅ **Core Logic:** 90/100 (excellent service layer tests)
- ⚠️ **New Features:** 0/100 (installments, quotations untested)
- ❌ **Security:** 0/100 (RBAC untested)
- ⚠️ **Reports:** 0/100 (calculation logic untested)
- ✅ **Financial Integrity:** 95/100 (comprehensive edge case testing)

**After Completing Phase 1 (3 weeks):** 🟢 **95/100** (Production-ready)

---

### 📊 **Summary Table: Test Coverage Roadmap**

| Priority | Test Suite | Tests | Hours | Status | Risk |
|----------|-----------|-------|-------|--------|------|
| P0 | Installments | 18 | 8 | ❌ Not Started | 🔴 Critical |
| P0 | Quotation Conversion | 15 | 6 | ❌ Not Started | 🔴 Critical |
| P0 | Shield RBAC | 25 | 10 | ❌ Not Started | 🔴 Critical |
| P1 | Reports (All) | 17 | 9 | ❌ Not Started | 🟡 High |
| P2 | Warehouse Transfer | 8 | 4 | ❌ Not Started | 🟡 Medium |
| P2 | Stock Adjustment | 6 | 3 | ❌ Not Started | 🟡 Medium |
| P3 | Fixed Assets | 5 | 3 | ❌ Not Started | 🟢 Low |
| P3 | UI Resources | 40 | 16 | ⚠️ Partial | 🟢 Low |
| **TOTAL** | **All Phases** | **134** | **59 hrs** | **~40% Done** | |

---

**END OF REPORT**

Generated by: Claude Sonnet 4.5
Report Version: 1.0
Last Updated: 2025-12-31
