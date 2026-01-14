# Comprehensive Testing Prompt for Plan Mode

## Context
I have a Laravel 12 + Filament 3 ERP system with 5 core services that handle all business logic. I need to create a complete, fresh test suite from scratch using Pest PHP. Ignore all existing tests in `tests/` directory as they may be broken or incorrect.

## Project Architecture

### Services to Test (from SERVICES_AND_LOGIC_ANALYSIS.md):

1. **StockService** (`app/Services/StockService.php`)
   - `recordMovement()` - Creates stock movement records
   - `postSalesInvoice()` - Posts sales invoice (deducts stock)
   - `postPurchaseInvoice()` - Posts purchase invoice (adds stock, updates costs)
   - `postSalesReturn()` - Posts sales return (restores stock)
   - `postPurchaseReturn()` - Posts purchase return (removes stock)
   - `postStockAdjustment()` - Posts stock adjustments (addition, subtraction, damage, gift)
   - `postWarehouseTransfer()` - Posts warehouse transfers (dual movements)
   - `getCurrentStock()` - Gets current stock level (with optional lock)
   - `validateStockAvailability()` - Validates stock before sale
   - `getStockValidationMessage()` - Gets validation message with stock info
   - `updateProductAvgCost()` - Updates weighted average cost
   - `updateProductPrice()` - Updates product selling prices
   - `convertToBaseUnit()` - Converts large unit to base unit

2. **TreasuryService** (`app/Services/TreasuryService.php`)
   - `recordTransaction()` - Creates treasury transaction (with negative balance prevention)
   - `postSalesInvoice()` - Posts sales invoice (creates collection transaction)
   - `postPurchaseInvoice()` - Posts purchase invoice (creates payment transaction)
   - `postSalesReturn()` - Posts sales return (creates refund transaction)
   - `postPurchaseReturn()` - Posts purchase return (creates refund transaction)
   - `postExpense()` - Posts expense (creates expense transaction)
   - `postRevenue()` - Posts revenue (creates income transaction)
   - `postFixedAssetPurchase()` - Posts fixed asset purchase
   - `recordInvoicePayment()` - Records subsequent payment on invoice (with installments)
   - `recordFinancialTransaction()` - Records standalone collection/payment (with discounts)
   - `recordEmployeeAdvance()` - Records employee advance payment
   - `getTreasuryBalance()` - Gets treasury balance (with lockForUpdate)
   - `getPartnerBalance()` - Gets partner balance from treasury transactions
   - `updatePartnerBalance()` - Recalculates partner balance

3. **InstallmentService** (`app/Services/InstallmentService.php`)
   - `generateInstallmentSchedule()` - Creates installment schedule for invoice
   - `applyPaymentToInstallments()` - Applies payment to installments (FIFO)
   - `updateOverdueInstallments()` - Updates overdue installment status

4. **FinancialReportService** (`app/Services/FinancialReportService.php`)
   - `generateReport()` - Main report generator
   - `calculateFixedAssetsValue()` - Calculates fixed assets
   - `calculateInventoryValue()` - Calculates inventory value at date
   - `calculateTotalDebtors()` - Calculates total debtors
   - `calculateTotalCreditors()` - Calculates total creditors
   - `calculateTotalCash()` - Calculates total cash
   - `calculateTotalSales()` - Calculates sales in date range
   - `calculateTotalPurchases()` - Calculates purchases in date range
   - `calculateSalesReturns()` - Calculates sales returns
   - `calculatePurchaseReturns()` - Calculates purchase returns
   - `calculateExpenses()` - Calculates expenses
   - `calculateRevenues()` - Calculates revenues
   - `calculateDiscountReceived()` - Calculates settlement discounts received
   - `calculateDiscountAllowed()` - Calculates settlement discounts allowed
   - `calculateShareholderCapital()` - Calculates shareholder capital
   - `calculateShareholderDrawings()` - Calculates shareholder drawings

5. **ReportService** (`app/Services/ReportService.php`)
   - `getPartnerStatement()` - Generates partner statement report
   - `calculateOpeningBalance()` - Calculates opening balance for partner
   - `fetchSalesInvoices()` - Fetches sales invoices for statement
   - `fetchInvoicePayments()` - Fetches payments for statement
   - `fetchSalesReturns()` - Fetches returns for statement
   - `getStockCard()` - Generates stock card report
   - `calculateOpeningStock()` - Calculates opening stock
   - `fetchStockMovements()` - Fetches stock movements for card
   - `getMovementReferenceNumber()` - Extracts reference number from movement

## Testing Requirements

### Test Framework
- Use **Pest PHP** (not PHPUnit)
- All tests in `tests/Feature/Services/` directory
- Use `RefreshDatabase` trait for database tests
- Use factories for test data creation

### Test Types Required

For EACH service method, create:

1. **Positive Tests** (Happy Path)
   - Normal operation with valid inputs
   - Expected successful outcomes
   - Verify correct data creation/updates

2. **Negative Tests** (Error Handling)
   - Invalid inputs (null, empty, wrong types)
   - Business rule violations (negative balance, insufficient stock)
   - Invalid states (posting already posted invoice)
   - Missing required data

3. **Edge Cases**
   - Zero values (amount, quantity)
   - Very large values (precision testing)
   - Very small values (0.0001 precision)
   - Boundary conditions
   - Concurrent operations (race conditions)
   - Transaction rollback scenarios

### Integration Tests (E2E)

Create end-to-end tests that verify:
- Complete invoice posting flow (stock + treasury + partner balance)
- Complete return flow (stock + treasury + partner balance)
- Payment application flow (treasury + invoice + installments)
- Report generation with real data
- Multi-step operations (purchase → sale → return → payment)

## Specific Test Scenarios

### StockService Tests

#### `postSalesInvoice()`
- ✅ Positive: Deducts stock correctly, creates negative movement
- ❌ Negative: Throws exception when stock insufficient
- ⚠️ Edge: Concurrent sales of same product, zero quantity, very large quantity

#### `postPurchaseInvoice()`
- ✅ Positive: Adds stock, updates avg_cost (weighted average), updates prices
- ❌ Negative: Invalid invoice status, missing items
- ⚠️ Edge: Large unit purchases (cost conversion), zero cost items, multiple purchases affecting avg_cost

#### `postStockAdjustment()`
- ✅ Positive: Addition adds stock, subtraction removes stock
- ❌ Negative: Subtraction when insufficient stock
- ⚠️ Edge: All adjustment types (addition, subtraction, damage, gift), zero quantity

#### `postWarehouseTransfer()`
- ✅ Positive: Creates dual movements (negative from source, positive to destination)
- ❌ Negative: Insufficient stock in source warehouse
- ⚠️ Edge: Same warehouse transfer, multiple products

#### `updateProductAvgCost()`
- ✅ Positive: Calculates weighted average correctly
- ⚠️ Edge: Zero cost purchases, single purchase, multiple purchases, purchases with different costs

### TreasuryService Tests

#### `recordTransaction()`
- ✅ Positive: Creates transaction, updates balance
- ❌ Negative: Throws exception when balance would go negative
- ⚠️ Edge: Zero amount, very large amount, concurrent transactions (lockForUpdate)

#### `postSalesInvoice()`
- ✅ Positive: Cash invoice creates collection, credit invoice doesn't create transaction
- ❌ Negative: Already posted invoice, invalid status
- ⚠️ Edge: Zero paid_amount, partial payment, full payment

#### `recordInvoicePayment()`
- ✅ Positive: Creates payment, updates invoice paid_amount, applies to installments
- ❌ Negative: Payment on draft invoice, payment exceeding remaining
- ⚠️ Edge: Payment with discount, partial payment, overpayment

#### `recordFinancialTransaction()`
- ✅ Positive: Collection increases treasury, decreases partner balance
- ❌ Negative: Invalid type, negative amount for collection
- ⚠️ Edge: Discount handling, zero amount, very large amounts

### InstallmentService Tests

#### `generateInstallmentSchedule()`
- ✅ Positive: Creates correct number of installments, correct amounts
- ❌ Negative: Draft invoice, already has schedule, zero remaining amount
- ⚠️ Edge: Rounding differences in last installment, different month lengths

#### `applyPaymentToInstallments()`
- ✅ Positive: FIFO payment application, marks installments as paid
- ❌ Negative: Payment on draft invoice
- ⚠️ Edge: Partial payment, overpayment, multiple installments

### FinancialReportService Tests

#### `generateReport()`
- ✅ Positive: All calculations correct, balances match
- ⚠️ Edge: Empty date ranges, very large date ranges, missing data

#### All calculation methods
- ✅ Positive: Correct calculations for each method
- ⚠️ Edge: Zero values, null values, date boundaries

### ReportService Tests

#### `getPartnerStatement()`
- ✅ Positive: Correct opening balance, transactions, closing balance
- ⚠️ Edge: No transactions, all transactions, date boundaries

#### `getStockCard()`
- ✅ Positive: Correct opening stock, movements, closing stock
- ⚠️ Edge: No movements, all warehouses, date boundaries

## Integration Test Scenarios

1. **Complete Sales Invoice Flow**
   - Create draft invoice → Post invoice → Verify stock deducted → Verify treasury transaction → Verify partner balance

2. **Complete Purchase Invoice Flow**
   - Create draft invoice → Post invoice → Verify stock added → Verify avg_cost updated → Verify treasury transaction

3. **Complete Return Flow**
   - Post sales invoice → Post sales return → Verify stock restored → Verify treasury refund → Verify partner balance updated

4. **Complete Payment Flow**
   - Post credit invoice → Record payment → Verify treasury transaction → Verify invoice paid_amount → Verify installments updated → Verify partner balance

5. **Complete Warehouse Transfer Flow**
   - Post transfer → Verify source warehouse stock decreased → Verify destination warehouse stock increased → Verify both movements created

6. **Complete Report Generation Flow**
   - Create transactions → Generate report → Verify all calculations match actual data

## Test Structure

```
tests/
├── Feature/
│   ├── Services/
│   │   ├── StockService/
│   │   │   ├── PostSalesInvoiceTest.php
│   │   │   ├── PostPurchaseInvoiceTest.php
│   │   │   ├── PostSalesReturnTest.php
│   │   │   ├── PostPurchaseReturnTest.php
│   │   │   ├── StockAdjustmentTest.php
│   │   │   ├── WarehouseTransferTest.php
│   │   │   ├── StockValidationTest.php
│   │   │   └── AverageCostTest.php
│   │   ├── TreasuryService/
│   │   │   ├── RecordTransactionTest.php
│   │   │   ├── PostSalesInvoiceTest.php
│   │   │   ├── PostPurchaseInvoiceTest.php
│   │   │   ├── PostReturnsTest.php
│   │   │   ├── InvoicePaymentTest.php
│   │   │   ├── FinancialTransactionTest.php
│   │   │   ├── BalanceTest.php
│   │   │   └── EdgeCasesTest.php
│   │   ├── InstallmentService/
│   │   │   ├── GenerateScheduleTest.php
│   │   │   ├── ApplyPaymentTest.php
│   │   │   └── OverdueTest.php
│   │   ├── FinancialReportService/
│   │   │   ├── GenerateReportTest.php
│   │   │   └── CalculationMethodsTest.php
│   │   └── ReportService/
│   │       ├── PartnerStatementTest.php
│   │       └── StockCardTest.php
│   └── Integration/
│       ├── InvoicePostingFlowTest.php
│       ├── ReturnFlowTest.php
│       ├── PaymentFlowTest.php
│       ├── WarehouseTransferFlowTest.php
│       └── ReportGenerationFlowTest.php
```

## Critical Business Rules to Test

1. **Stock Integrity**
   - Stock cannot go negative (except returns)
   - All stock changes must go through stock_movements table
   - Stock is always in base units

2. **Treasury Integrity**
   - Treasury balance cannot go negative
   - All cash movements must go through treasury_transactions table
   - lockForUpdate() prevents race conditions

3. **Partner Balance**
   - Calculated from invoices, returns, and payments
   - Cash transactions don't affect partner balance
   - Credit transactions affect partner balance

4. **Weighted Average Cost**
   - Calculated from purchase movements only
   - Formula: SUM(cost * quantity) / SUM(quantity)
   - Large unit costs must be divided by factor

5. **Transaction Atomicity**
   - If any step fails, entire operation rolls back
   - No partial updates

## Test Data Requirements

- Use factories for all models
- Create realistic test scenarios
- Use Arabic text where appropriate (descriptions, names)
- Test with DECIMAL(15,4) precision values
- Test with ULID primary keys

## Success Criteria

- ✅ All service methods have positive, negative, and edge case tests
- ✅ All integration flows are tested end-to-end
- ✅ All business rules are validated
- ✅ All calculations are verified with expected values
- ✅ All error conditions are tested
- ✅ Test coverage > 95% for services
- ✅ All tests pass consistently
- ✅ Tests are readable and maintainable


## Deliverables

1. Complete Pest test suite for all 5 services
2. Integration tests for all major flows
3. Test helper functions/utilities
4. Documentation of test coverage
5. All tests passing with clear, descriptive names

---

**Start by installing Pest (if needed) and creating the test structure, then systematically test each service method with positive, negative, and edge cases. Focus on ensuring 100% logic coverage and correct calculations.**
