# Comprehensive Test Plan - Mawared ERP System
## Business Logic, Services, and Calculations Testing

---

## Table of Contents
1. [Testing Overview](#testing-overview)
2. [Phase 1: Service Layer Tests](#phase-1-service-layer-tests)
3. [Phase 2: Model Business Logic Tests](#phase-2-model-business-logic-tests)
4. [Phase 3: Integration Tests](#phase-3-integration-tests)
5. [Phase 4: Data Integrity Tests](#phase-4-data-integrity-tests)
6. [Phase 5: Calculation Accuracy Tests](#phase-5-calculation-accuracy-tests)
7. [Phase 6: Concurrency Tests](#phase-6-concurrency-tests)
8. [Phase 7: Report Accuracy Tests](#phase-7-report-accuracy-tests)
9. [Testing Checklist](#testing-checklist)

---

## Testing Overview

### Test Environment Setup
- **Framework**: PHPUnit (Laravel default)
- **Database**: SQLite in-memory for speed, or MySQL for exact production parity
- **Precision**: All financial tests must validate BCMath precision (4 decimals)
- **Fixtures**: Use factories for consistent test data
- **Assertions**: Custom assertions for money comparisons (bccomp)

### Key Testing Principles
1. **Financial Precision**: All money calculations use BCMath with 4 decimal places
2. **Atomicity**: Test database transactions and rollback scenarios
3. **Concurrency**: Test locking mechanisms and race conditions
4. **Immutability**: Verify posted records cannot be modified
5. **Data Integrity**: Validate foreign keys and business rules

---

## Phase 1: Service Layer Tests

### 1.1 InstallmentService Tests (15 tests)

#### Test Class: `InstallmentServiceTest`

**1.1.1 generateInstallmentSchedule() - Success Scenarios**
```
✓ generates equal installments for invoice with exact division
  - Invoice: 12000 EGP, 12 months → 1000 EGP each
  - Assert: all amounts equal, dates sequential

✓ handles rounding by adjusting last installment
  - Invoice: 10000 EGP, 3 months → 3333.33, 3333.33, 3333.34
  - Assert: sum equals total exactly

✓ creates correct number of installments
  - Assert: count matches requested months

✓ sets due dates correctly with monthly intervals
  - Assert: each due_date is exactly 1 month after previous

✓ links installments to correct invoice and partner
  - Assert: sales_invoice_id and partner_id populated
```

**1.1.2 generateInstallmentSchedule() - Validation**
```
✓ prevents installments on non-posted invoice
  - Assert: throws exception with draft invoice

✓ prevents installments on fully paid invoice
  - Assert: throws exception when remaining_amount = 0

✓ prevents duplicate installment generation
  - Assert: second call throws exception

✓ validates months parameter is positive
  - Assert: throws exception for months <= 0
```

**1.1.3 applyPaymentToInstallments() - FIFO Allocation**
```
✓ applies payment to oldest installment first
  - 3 installments, pay 1 → assert first fully paid, others pending

✓ spreads payment across multiple installments
  - 3 installments of 1000, pay 2500 → assert first 2 paid, third partial

✓ handles exact payment amount
  - Pay exact installment amount → assert paid_amount equals amount

✓ prevents overpayment of individual installment
  - Assert: paid_amount never exceeds amount
```

**1.1.4 applyPaymentToInstallments() - Concurrency**
```
✓ uses lockForUpdate to prevent race conditions
  - Mock concurrent payment → assert locking called

✓ handles payment within transaction
  - Assert: rollback on error leaves installments unchanged
```

**1.1.5 updateOverdueInstallments() - Status Updates**
```
✓ marks pending installments as overdue after due date
  - Create installment with past due_date → run update → assert overdue

✓ does not change status of paid installments
  - Paid installment past due → assert remains paid
```

---

### 1.2 TreasuryService Tests (25 tests)

#### Test Class: `TreasuryServiceTest`

**1.2.1 recordTransaction() - Balance Validation**
```
✓ prevents transaction causing negative balance
  - Treasury balance 1000, attempt withdraw 1500 → assert exception

✓ allows transaction within available balance
  - Balance 1000, withdraw 800 → assert success, new balance 200

✓ handles zero balance edge case
  - Balance 0, attempt withdraw 1 → assert exception

✓ excludes discount type from balance check
  - Record discount transaction → assert balance unchanged
```

**1.2.2 recordTransaction() - Locking**
```
✓ uses lockForUpdate on balance calculation
  - Assert: lock acquired before balance check

✓ handles nested transactions properly
  - Nested DB::transaction → assert no deadlock, correct behavior
```

**1.2.3 getTreasuryBalance() - Calculation**
```
✓ calculates balance as sum of all transactions
  - Create +1000, -300, +500 → assert balance 1200

✓ excludes discount type transactions
  - Create +1000 collection, +200 discount → assert balance 1000

✓ respects treasury_id filter
  - Multiple treasuries → assert each balance separate

✓ uses locking when requested
  - Assert: lockForUpdate called with lock=true parameter
```

**1.2.4 postSalesInvoice() - Treasury Impact**
```
✓ creates treasury transaction for cash sale
  - Cash invoice 5000 → assert treasury +5000

✓ creates transaction only for paid_amount on credit sale
  - Total 10000, paid 3000 → assert treasury +3000

✓ does not create transaction for full credit sale
  - paid_amount = 0 → assert no treasury transaction

✓ uses correct transaction type (COLLECTION)
  - Assert: type = 'collection'

✓ links to invoice via reference polymorphic
  - Assert: reference_type = SalesInvoice, reference_id = invoice.id
```

**1.2.5 postPurchaseInvoice() - Treasury Impact**
```
✓ creates negative treasury transaction for cash purchase
  - Cash invoice 3000 → assert treasury -3000

✓ creates transaction only for paid_amount on credit purchase
  - Total 10000, paid 2000 → assert treasury -2000

✓ does not create transaction for full credit purchase
  - paid_amount = 0 → assert no treasury transaction
```

**1.2.6 recordInvoicePayment() - Subsequent Payments**
```
✓ creates treasury transaction for payment amount
  - Pay 1000 on credit invoice → assert treasury +1000 (sales)
  - Pay 1000 on credit invoice → assert treasury -1000 (purchase)

✓ creates separate discount transaction
  - Payment 1000, discount 50 → assert 2 transactions created
  - Assert: one 'collection/payment', one 'discount'

✓ discount transaction does not affect treasury balance
  - Assert: getTreasuryBalance excludes discount

✓ updates invoice paid_amount correctly
  - Invoice paid 1000, pay additional 500 → assert paid_amount = 1500

✓ updates invoice remaining_amount correctly
  - Total 5000, paid 1000, pay 1500 → assert remaining = 2500

✓ applies payment to installments if they exist
  - Mock applyPaymentToInstallments → assert called

✓ settlement discount = amount + discount
  - Pay 1000, discount 50 → assert total debt reduction = 1050
```

**1.2.7 postSalesReturn() - Cash vs Credit**
```
✓ creates negative treasury transaction for cash return
  - Cash return 1000 → assert treasury -1000

✓ does not create treasury transaction for credit return
  - Credit return 1000 → assert no treasury transaction

✓ prevents duplicate return posting
  - Post return twice → assert exception or idempotent
```

**1.2.8 postPurchaseReturn() - Cash vs Credit**
```
✓ creates positive treasury transaction for cash return
  - Cash return 500 → assert treasury +500

✓ does not create treasury transaction for credit return
  - Credit return 500 → assert no treasury transaction
```

**1.2.9 postFixedAssetPurchase() - Funding Methods**
```
✓ creates negative treasury transaction for cash funding
  - Asset 50000, funding='cash' → assert treasury -50000

✓ does not create treasury transaction for payable funding
  - Funding='payable' → assert no treasury transaction

✓ does not create treasury transaction for equity funding
  - Funding='equity' → assert no treasury transaction
```

---

### 1.3 StockService Tests (20 tests)

#### Test Class: `StockServiceTest`

**1.3.1 recordMovement() - Movement Creation**
```
✓ creates positive movement for stock IN
  - Purchase 100 units → assert quantity = 100

✓ creates negative movement for stock OUT
  - Sale 50 units → assert quantity = -50

✓ stores cost_at_time for COGS tracking
  - Product avg_cost 10, sell 5 → assert cost_at_time = 10

✓ converts all quantities to base unit
  - Sell 2 large units (factor 12) → assert quantity = -24

✓ links to reference document polymorphically
  - Assert: reference_type and reference_id populated
```

**1.3.2 getCurrentStock() - Stock Calculation**
```
✓ calculates stock as sum of all movements
  - Create +100, -30, +20, -10 → assert stock = 80

✓ uses lockForUpdate when lock parameter is true
  - Assert: lock acquired on query

✓ filters by warehouse and product
  - Multiple products/warehouses → assert correct isolation

✓ handles zero stock correctly
  - No movements → assert stock = 0
```

**1.3.3 validateStockAvailability() - Overselling Prevention**
```
✓ allows sale when stock is sufficient
  - Stock 100, sell 50 → assert no exception

✓ prevents sale when stock is insufficient
  - Stock 30, sell 50 → assert exception

✓ handles exact stock amount
  - Stock 100, sell 100 → assert allowed

✓ validates in base unit
  - Stock 50 pieces, sell 5 large units (60 pieces) → assert exception
```

**1.3.4 updateProductAvgCost() - Weighted Average**
```
✓ calculates weighted average from purchases
  - Purchase 10 @ 5, then 20 @ 8 → avg = (50+160)/30 = 7
  - Assert: avg_cost = 7.0000

✓ includes purchase returns (negative quantities)
  - Purchase 100 @ 10, return 20 @ 10 → avg from 80 units
  - Assert: avg_cost correct

✓ handles zero stock scenario
  - All stock sold/returned → assert avg_cost = 0 or unchanged

✓ uses BCMath for precision
  - Assert: 4 decimal places maintained
```

**1.3.5 postSalesInvoice() - COGS and Stock**
```
✓ calculates COGS as avg_cost * quantity
  - Avg cost 12.50, sell 10 units → COGS = 125.00
  - Assert: cost_total on invoice = 125.0000

✓ creates negative stock movements for all items
  - Invoice with 3 items → assert 3 movements with negative quantities

✓ validates stock with lockForUpdate inside transaction
  - Mock to verify lock acquired

✓ prevents overselling with concurrent sales
  - Simulate race condition → assert one sale fails

✓ stores COGS on invoice for profitability
  - Assert: invoice.cost_total persisted to database
```

**1.3.6 postPurchaseInvoice() - Cost and Stock**
```
✓ converts large unit cost to base unit cost
  - Large cost 120, factor 12 → base cost = 10
  - Assert: movement cost_at_time = 10.0000

✓ creates positive stock movements for all items
  - Invoice with 2 items → assert 2 movements with positive quantities

✓ updates product prices if new_selling_price set
  - Item has new_selling_price 50 → assert product.retail_price = 50

✓ recalculates avg_cost after posting
  - Assert: updateProductAvgCost called for each product
```

**1.3.7 postSalesReturn() - COGS Reversal**
```
✓ reverses COGS on original invoice
  - Original COGS 500, return 200 → new COGS = 300
  - Assert: invoice.cost_total updated

✓ creates positive stock movements
  - Return 10 units → assert movement quantity = +10

✓ handles proportional COGS reversal
  - Assert: reversal = COGS * (return_qty / invoice_qty)
```

**1.3.8 postPurchaseReturn() - Stock Reduction**
```
✓ creates negative stock movements
  - Return 20 units → assert movement quantity = -20

✓ validates stock availability before return
  - Stock 10, return 20 → assert exception

✓ recalculates avg_cost after return
  - Assert: updateProductAvgCost called
```

---

### 1.4 CapitalService Tests (15 tests)

#### Test Class: `CapitalServiceTest`

**1.4.1 createInitialPeriod() - Period Creation**
```
✓ creates equity_period with start_date
  - Assert: period.start_date = provided date

✓ locks partner percentages at period start
  - 3 partners with 40%, 30%, 30% → assert pivot records created

✓ validates percentages sum to 100%
  - Partners sum to 95% → assert exception
  - Partners sum to 105% → assert exception

✓ calculates capital_at_start for each partner
  - Assert: pivot.capital_at_start = partner.current_capital
```

**1.4.2 calculatePeriodProfit() - Income Statement**
```
✓ calculates total revenue correctly
  - Revenue = Sales - Sales Returns + Other Revenue
  - Assert: period.total_revenue matches calculation

✓ calculates total expenses correctly
  - Expenses = Purchases - Purchase Returns + Operating + Salaries + Depreciation + Commissions
  - Assert: period.total_expenses matches calculation

✓ calculates net profit correctly
  - Net Profit = Revenue - Expenses
  - Assert: period.net_profit matches calculation

✓ handles zero revenue/expenses
  - Assert: doesn't crash, returns 0
```

**1.4.3 allocateProfitToPartners() - Distribution**
```
✓ distributes profit by locked percentages
  - Profit 10000, partner 1 has 40% → allocation = 4000
  - Assert: pivot.profit_allocated = 4000.0000

✓ creates PROFIT_ALLOCATION treasury transactions
  - Assert: transaction type correct, amount matches allocation

✓ updates partner current_capital
  - Partner capital 50000, allocation 4000 → new capital = 54000
  - Assert: partner.current_capital = 54000.0000

✓ records in pivot table
  - Assert: equity_period_partners.profit_allocated populated

✓ uses BCMath for precision
  - Assert: no rounding errors, 4 decimals maintained
```

**1.4.4 injectCapital() - Capital Injection**
```
✓ auto-closes current period before injection
  - Assert: current period end_date set, status = closed

✓ creates CAPITAL_DEPOSIT transaction for cash
  - Cash injection 20000 → assert treasury +20000

✓ creates ASSET_CONTRIBUTION transaction for asset
  - Asset injection 30000 → assert no treasury transaction

✓ updates partner current_capital
  - Capital 50000, inject 20000 → new capital = 70000
  - Assert: partner.current_capital = 70000.0000

✓ recalculates all equity percentages
  - Assert: recalculateEquityPercentages called

✓ creates new period with updated percentages
  - Assert: new period created with new equity_percentage values
```

**1.4.5 recordDrawing() - Partner Withdrawal**
```
✓ creates negative treasury transaction
  - Drawing 5000 → assert treasury -5000
  - Assert: type = PARTNER_DRAWING

✓ reduces partner current_capital
  - Capital 50000, drawing 5000 → new capital = 45000
  - Assert: partner.current_capital = 45000.0000

✓ tracks in current period pivot
  - Assert: pivot.drawings_taken updated
```

**1.4.6 recalculateEquityPercentages() - Percentage Calculation**
```
✓ calculates percentage as (capital / total) * 100
  - 3 partners: 60000, 30000, 10000 → percentages: 60%, 30%, 10%
  - Assert: each partner.equity_percentage correct

✓ handles zero total capital edge case
  - Assert: doesn't crash, sets 0% or equal distribution

✓ percentages sum to 100% (within rounding)
  - Assert: SUM(equity_percentage) ≈ 100.00
```

---

### 1.5 DepreciationService Tests (8 tests)

#### Test Class: `DepreciationServiceTest`

**1.5.1 calculateAssetDepreciation() - Formula**
```
✓ calculates straight-line monthly depreciation
  - Cost 120000, salvage 12000, life 10 years
  - Monthly = (120000 - 12000) / 120 = 900
  - Assert: depreciation = 900.0000

✓ uses BCMath for precision
  - Assert: 4 decimal places maintained

✓ handles zero salvage value
  - Cost 60000, salvage 0, life 5 years → monthly = 1000
  - Assert: depreciation = 1000.0000

✓ handles zero useful life
  - Life = 0 or null → assert returns 0 or exception
```

**1.5.2 processMonthlyDepreciation() - Batch Processing**
```
✓ processes all active assets with useful_life
  - Create 5 active assets → assert all depreciated

✓ skips fully depreciated assets
  - Accumulated = depreciable amount → assert skipped

✓ ensures depreciation doesn't exceed depreciable amount
  - Close to full depreciation → assert final amount capped

✓ creates DEPRECIATION_EXPENSE treasury transactions
  - Assert: transaction type, amount, reference correct

✓ updates accumulated_depreciation and last_depreciation_date
  - Assert: both fields updated correctly
```

---

### 1.6 CommissionService Tests (10 tests)

#### Test Class: `CommissionServiceTest`

**1.6.1 calculateCommission() - Calculation**
```
✓ calculates commission as total * rate / 100
  - Invoice 10000, rate 5% → commission = 500
  - Assert: invoice.commission_amount = 500.0000

✓ handles zero rate
  - Rate 0% → commission = 0
  - Assert: commission = 0.0000

✓ handles decimal rates
  - Rate 2.5% → assert correct calculation

✓ uses BCMath for precision
  - Assert: 4 decimals maintained
```

**1.6.2 payCommission() - Payment**
```
✓ validates invoice is posted before payment
  - Draft invoice → assert exception

✓ validates commission not already paid
  - Attempt double payment → assert exception

✓ creates COMMISSION_PAYOUT treasury transaction
  - Pay commission 500 → assert treasury -500
  - Assert: type = COMMISSION_PAYOUT

✓ marks commission_paid = true
  - Assert: invoice.commission_paid = true after payment
```

**1.6.3 reverseCommission() - Reversal on Return**
```
✓ calculates proportional reversal
  - Invoice 10000, commission 500, return 2000
  - Reversal = 500 * (2000 / 10000) = 100
  - Assert: reversal_amount = 100.0000

✓ creates COMMISSION_REVERSAL transaction
  - Assert: treasury +100 (positive)

✓ reduces original invoice commission_amount
  - Original 500, reversal 100 → new commission = 400
  - Assert: invoice.commission_amount = 400.0000

✓ marks unpaid if fully reversed
  - Full return → assert commission_paid = false
```

---

### 1.7 ReportService Tests (12 tests)

#### Test Class: `ReportServiceTest`

**1.7.1 calculateOpeningBalance() - Partner Statement**
```
✓ calculates balance before date range
  - Transactions before start_date → assert opening balance correct

✓ includes sales invoices remaining amount
  - Credit sales → assert added to balance (customer owes)

✓ subtracts payments
  - Collections → assert subtracted from balance

✓ subtracts returns
  - Credit returns → assert subtracted

✓ includes discount in payment calculations
  - Payment 1000, discount 50 → assert total 1050 subtracted

✓ handles different partner types
  - Customer vs Supplier vs Shareholder → assert different formulas
```

**1.7.2 getPartnerStatement() - Transaction History**
```
✓ merges invoices, payments, returns chronologically
  - Create mixed transactions → assert sorted by date

✓ calculates running balance through transactions
  - Assert: each row balance = previous + current

✓ returns debit/credit movements correctly
  - Assert: customer invoices are debit, payments are credit

✓ filters by date range
  - Date range provided → assert only transactions in range
```

**1.7.3 getStockCard() - Product Movement**
```
✓ calculates opening stock from movements before date
  - Movements before start_date → assert opening correct

✓ tracks IN/OUT movements with running balance
  - Assert: balance updates correctly for each movement

✓ links to reference documents
  - Assert: reference_type and reference_id populated

✓ filters by date range
  - Assert: only movements in range
```

---

### 1.8 FinancialReportService Tests (15 tests)

#### Test Class: `FinancialReportServiceTest`

**1.8.1 generateReport() - Income Statement**
```
✓ calculates net sales correctly
  - Net Sales = Total Sales - Sales Returns
  - Assert: net_sales matches calculation

✓ calculates COGS from invoice cost_total
  - Sum all sales_invoices.cost_total → assert matches

✓ calculates gross profit correctly
  - Gross = Net Sales - COGS
  - Assert: gross_profit matches

✓ calculates operating expenses correctly
  - OpEx = Expenses + Commissions + Discounts Allowed
  - Assert: operating_expenses matches

✓ calculates net profit correctly
  - Net = Gross - OpEx + Other Revenue + Discounts Received
  - Assert: net_profit matches

✓ handles zero values
  - No sales/expenses → assert doesn't crash
```

**1.8.2 generateReport() - Balance Sheet**
```
✓ calculates fixed assets book value
  - Book Value = Purchase - Accumulated Depreciation
  - Assert: fixed_assets matches

✓ calculates inventory value using avg_cost
  - Inventory = SUM(avg_cost * quantity) for positive stock
  - Assert: inventory matches

✓ calculates total debtors (customers with positive balance)
  - Assert: debtors = SUM of partner balances > 0 (non-shareholders)

✓ calculates total creditors (suppliers with negative balance)
  - Assert: creditors = ABS(SUM of partner balances < 0) (non-shareholders)

✓ calculates cash from treasury
  - Cash = SUM of treasury transactions (excluding discounts)
  - Assert: cash matches getTreasuryBalance

✓ calculates shareholder capital
  - Equity = SUM of current_capital for shareholders
  - Assert: shareholder_equity matches

✓ calculates retained earnings
  - Retained = Net Profit - Drawings
  - Assert: retained_earnings matches
```

**1.8.3 Calculation Methods**
```
✓ calculateInventoryValue() - optimized query
  - Assert: single query, date filtering works

✓ calculateCOGS() - sums cost_total
  - Assert: SUM(cost_total) from posted sales invoices

✓ calculateCommissionsPaid() - sums paid commissions
  - Assert: SUM(commission_amount) WHERE commission_paid = true
```

---

## Phase 2: Model Business Logic Tests

### 2.1 Partner Model Tests (20 tests)

#### Test Class: `PartnerTest`

**2.1.1 calculateBalance() - Customer Type**
```
✓ calculates balance from opening balance
  - Opening 1000, no transactions → balance = 1000

✓ includes credit sales remaining amount
  - Credit sale 5000 (remaining 5000) → balance increases by 5000

✓ excludes cash sales (remaining = 0)
  - Cash sale 3000 (remaining 0) → balance unchanged

✓ subtracts credit returns
  - Credit return 500 → balance decreases by 500

✓ excludes cash returns
  - Cash return 200 → balance unchanged (treasury only)

✓ subtracts collection payments (amount only)
  - Collection 1000 → balance decreases by 1000

✓ subtracts settlement discounts
  - Payment 1000, discount 50 → balance decreases by 1050

✓ handles financial discount transactions
  - Discount transaction 100 → balance decreases by 100

✓ handles complex scenario
  - Opening 1000, credit sale 5000, collection 2000, return 500
  - Balance = 1000 + 5000 - 2000 - 500 = 3500
  - Assert: balance = 3500.0000
```

**2.1.2 calculateBalance() - Supplier Type**
```
✓ includes credit purchases remaining amount
  - Credit purchase 3000 (remaining 3000) → balance increases by 3000

✓ excludes cash purchases
  - Cash purchase 1000 → balance unchanged

✓ subtracts credit returns
  - Credit return 300 → balance decreases by 300

✓ subtracts payment transactions
  - Payment 1000 (negative in DB) → balance decreases by 1000

✓ handles complex scenario
  - Opening 2000, credit purchase 5000, payment 1500, return 500
  - Balance = 2000 + 5000 - 1500 - 500 = 5000
  - Assert: balance = 5000.0000
```

**2.1.3 calculateBalance() - Shareholder Type**
```
✓ calculates as opening + SUM of all treasury transactions
  - Capital deposit 100000, drawing 5000 → balance = 95000
  - Assert: balance = 95000.0000

✓ includes profit allocations
  - Profit allocation 10000 → balance increases

✓ includes asset contributions
  - Asset contribution 50000 → balance increases
```

**2.1.4 recalculateCapital() - Capital Calculation**
```
✓ sums capital transactions
  - Deposit 50000, asset 30000, profit 10000, drawing -5000
  - Capital = 85000
  - Assert: current_capital = 85000.0000

✓ filters by transaction types
  - Assert: only capital-related types included
```

**2.1.5 Deletion Protection**
```
✓ prevents deletion if has invoices
  - Create invoice → attempt delete → assert exception

✓ prevents deletion if has returns
  - Create return → attempt delete → assert exception

✓ prevents deletion if has transactions
  - Create transaction → attempt delete → assert exception

✓ prevents deletion if has payments
  - Create payment → attempt delete → assert exception

✓ allows deletion if no related records
  - New partner → delete → assert success
```

---

### 2.2 SalesInvoice Model Tests (15 tests)

#### Test Class: `SalesInvoiceTest`

**2.2.1 Status Methods**
```
✓ isPosted() returns true when status = posted
✓ isDraft() returns true when status = draft
✓ isFullyPaid() returns true when remaining = 0
✓ isPartiallyPaid() returns true when 0 < paid < total
```

**2.2.2 Profit Calculations**
```
✓ getGrossProfit() = total - cost_total
  - Total 5000, COGS 3000 → gross = 2000
  - Assert: gross_profit = 2000.0000

✓ getNetProfit() = gross_profit - commission_amount
  - Gross 2000, commission 250 → net = 1750
  - Assert: net_profit = 1750.0000

✓ getProfitMargin() = (net_profit / total) * 100
  - Net 1750, total 5000 → margin = 35%
  - Assert: profit_margin = 35.0000
```

**2.2.3 Discount Calculations**
```
✓ fixed discount returns discount_value
  - Type 'fixed', value 100 → discount = 100

✓ percentage discount calculates from subtotal
  - Type 'percentage', value 10, subtotal 5000 → discount = 500
  - Assert: calculated_discount = 500.0000
```

**2.2.4 Payment Tracking**
```
✓ getTotalPaid() = paid_amount + SUM(payments.amount)
  - Initial paid 1000, 2 payments of 500 → total paid = 2000
  - Assert: total_paid = 2000.0000

✓ getCurrentRemaining() = (total - returns) - total_paid
  - Total 5000, return 500, paid 2000 → remaining = 2500
  - Assert: current_remaining = 2500.0000
```

**2.2.5 Immutability**
```
✓ prevents updating posted invoice (non-payment fields)
  - Update partner_id on posted → assert exception

✓ allows updating payment fields on posted invoice
  - Update paid_amount, remaining_amount → assert success

✓ allows updating commission fields
  - Update commission_paid, commission_amount → assert success

✓ prevents updating if has installments
  - Create installments → update → assert exception

✓ prevents deletion if has stock movements
  - Post invoice → delete → assert exception

✓ prevents deletion if has treasury transactions
  - Post invoice → delete → assert exception
```

---

### 2.3 PurchaseInvoice Model Tests (15 tests)

Similar structure to SalesInvoice tests (without profit/commission tests):
- Status methods
- Discount calculations
- Payment tracking
- Immutability

---

### 2.4 Product Model Tests (10 tests)

#### Test Class: `ProductTest`

**2.4.1 Unit Conversion**
```
✓ convertToBaseUnit() with small unit returns quantity as-is
  - Unit = small, quantity 50 → base = 50

✓ convertToBaseUnit() with large unit multiplies by factor
  - Unit = large, quantity 5, factor 12 → base = 60

✓ handles zero quantity
  - Quantity 0 → base = 0

✓ handles decimal quantities
  - Quantity 2.5, factor 12 → base = 30
```

**2.4.2 Auto-generation**
```
✓ generates unique barcode on create
  - Create product → assert barcode format BC{timestamp}{random}

✓ generates unique SKU on create
  - Create product → assert SKU format SKU{timestamp}{random}

✓ generates large_barcode if large_unit_id set
  - Set large_unit_id → assert large_barcode format LB{timestamp}{random}

✓ regenerates large_barcode if large_unit_id added later
  - Update to add large_unit_id → assert large_barcode generated
```

**2.4.3 Validation**
```
✓ prevents negative min_stock
  - Set min_stock = -10 → assert exception or validation error

✓ prevents negative prices
  - Set retail_price = -50 → assert exception
```

**2.4.4 Stock Accessor**
```
✓ getStockAttribute() returns SUM of movements
  - Create movements +100, -30, +20 → assert stock = 90
```

---

### 2.5 Installment Model Tests (8 tests)

#### Test Class: `InstallmentTest`

**2.5.1 Status Logic**
```
✓ returns 'overdue' if due_date < today AND status = pending
  - Due date yesterday, pending → assert status = 'overdue'

✓ returns actual status if not overdue condition
  - Due date tomorrow, pending → assert status = 'pending'

✓ paid status never changes
  - Due date yesterday, paid → assert status = 'paid'
```

**2.5.2 Helper Methods**
```
✓ isPaid() returns true when paid_amount = amount
✓ isOverdue() returns true when overdue
```

**2.5.3 Immutability**
```
✓ prevents changing sales_invoice_id
  - Update invoice_id → assert exception

✓ prevents changing amount, due_date, installment_number
  - Update any immutable field → assert exception

✓ prevents deletion if paid_amount > 0
  - Pay installment partially → delete → assert exception
```

**2.5.4 Remaining Amount**
```
✓ getRemainingAmountAttribute() = amount - paid_amount
  - Amount 1000, paid 300 → remaining = 700
  - Assert: uses BCMath, remaining = 700.0000
```

---

### 2.6 FixedAsset Model Tests (6 tests)

#### Test Class: `FixedAssetTest`

**2.6.1 Depreciation**
```
✓ calculateMonthlyDepreciation() uses straight-line
  - Cost 120000, salvage 12000, life 10 → monthly = 900
  - Assert: monthly = 900.0000

✓ getBookValue() = cost - accumulated
  - Cost 120000, accumulated 36000 → book value = 84000
  - Assert: book_value = 84000.0000

✓ needsDepreciation() returns true if no last_depreciation_date
  - New asset → assert needs depreciation

✓ needsDepreciation() returns true if last month different
  - Last depreciation 2 months ago → assert needs depreciation

✓ needsDepreciation() returns false if already depreciated this month
  - Last depreciation this month → assert doesn't need
```

**2.6.2 Status**
```
✓ isDraft() and isPosted() work correctly
  - Draft asset → isPosted false
  - Posted asset → isPosted true
```

---

### 2.7 TreasuryTransaction Model Tests (4 tests)

#### Test Class: `TreasuryTransactionTest`

```
✓ stores amount with 4 decimal precision
  - Create transaction 1234.5678 → assert stored correctly

✓ positive amount for income types
  - Collection transaction → assert amount > 0

✓ negative amount for expense types
  - Payment transaction → assert amount < 0

✓ polymorphic reference works
  - Reference SalesInvoice → assert reference_type and id correct
```

---

### 2.8 StockMovement Model Tests (4 tests)

#### Test Class: `StockMovementTest`

```
✓ positive quantity for IN movements
  - Purchase movement → assert quantity > 0

✓ negative quantity for OUT movements
  - Sale movement → assert quantity < 0

✓ stores cost_at_time for COGS
  - Movement → assert cost_at_time = product avg_cost at time

✓ polymorphic reference works
  - Reference SalesInvoice → assert reference_type and id correct
```

---

## Phase 3: Integration Tests

### 3.1 Sales Invoice Complete Lifecycle (10 tests)

#### Test Class: `SalesInvoiceLifecycleTest`

**3.1.1 Cash Sale Flow**
```
✓ draft → post → verify all effects
  - Create draft → post
  - Assert: stock decreased, treasury increased, status posted
  - Assert: COGS calculated and stored
  - Assert: paid_amount = total, remaining = 0

✓ cash sale with commission
  - Post → pay commission
  - Assert: commission_paid true, treasury decreased

✓ cash sale with return
  - Post → create cash return → post return
  - Assert: stock restored, treasury decreased (refund)
  - Assert: COGS reversed on original invoice
```

**3.1.2 Credit Sale Flow**
```
✓ credit sale → subsequent payments → full payment
  - Post credit invoice (remaining = total)
  - Pay 50% → assert remaining = 50%, treasury increased by payment
  - Pay remaining 50% → assert fully paid, remaining = 0

✓ credit sale with settlement discount
  - Pay 1000 with 50 discount
  - Assert: paid_amount increased by 1000 only
  - Assert: remaining decreased by 1050 (payment + discount)
  - Assert: partner balance decreased by 1050
  - Assert: treasury increased by 1000 (cash only)
  - Assert: discount transaction created (not affecting treasury)

✓ credit sale with return
  - Post → create credit return → post return
  - Assert: stock restored, NO treasury transaction
  - Assert: partner balance reduced by return amount
  - Assert: COGS reversed on original invoice
```

**3.1.3 Installment Flow**
```
✓ create installments → pay sequentially
  - Post invoice → generate installments
  - Pay first installment → assert first paid, others pending
  - Pay partial on second → assert partial payment recorded
  - Pay remaining → assert all paid

✓ installment with settlement discount
  - Pay installment 1000 with discount 20
  - Assert: installment paid_amount = 1000
  - Assert: invoice paid_amount increased by 1000
  - Assert: invoice remaining decreased by 1020
```

**3.1.4 Multi-item Invoice**
```
✓ invoice with multiple products → post
  - 3 different products with different avg costs
  - Assert: COGS = SUM of (avg_cost * quantity) for all items
  - Assert: all stock movements created
  - Assert: gross profit = total - COGS

✓ multi-item invoice with partial return
  - Invoice with items A, B, C → post
  - Return only item B → post return
  - Assert: stock for B restored, A and C unchanged
  - Assert: COGS reversed proportionally for item B
```

---

### 3.2 Purchase Invoice Complete Lifecycle (10 tests)

#### Test Class: `PurchaseInvoiceLifecycleTest`

**3.2.1 Cash Purchase Flow**
```
✓ draft → post → verify all effects
  - Create draft → post
  - Assert: stock increased, treasury decreased, status posted
  - Assert: avg_cost updated

✓ cash purchase with return
  - Post → create cash return → post return
  - Assert: stock reduced, treasury increased (refund)
  - Assert: avg_cost recalculated
```

**3.2.2 Credit Purchase Flow**
```
✓ credit purchase → subsequent payments → full payment
  - Post credit invoice (remaining = total)
  - Pay 50% → assert remaining = 50%, treasury decreased
  - Pay remaining 50% → assert fully paid, remaining = 0

✓ credit purchase with settlement discount
  - Pay 2000 with 100 discount
  - Assert: paid_amount increased by 2000
  - Assert: remaining decreased by 2100
  - Assert: partner balance decreased by 2100
  - Assert: treasury decreased by 2000
```

**3.2.3 Unit Conversion**
```
✓ purchase in large unit → verify base unit conversion
  - Product: 1 carton = 12 pieces
  - Purchase: 5 cartons @ 120 per carton
  - Assert: stock movement quantity = 60 pieces
  - Assert: cost_at_time = 10 per piece (120 / 12)

✓ avg_cost calculation with mixed units
  - Purchase 10 pieces @ 5
  - Purchase 5 large units (60 pieces) @ 120 per large (10 per piece)
  - Assert: avg_cost = (50 + 600) / 70 = 9.2857
```

**3.2.4 Price Update**
```
✓ purchase with new_selling_price updates product
  - Item has new_selling_price 50
  - Post invoice
  - Assert: product.retail_price = 50

✓ purchase without new_selling_price keeps existing price
  - new_selling_price null
  - Post invoice
  - Assert: product.retail_price unchanged
```

**3.2.5 Multi-item Purchase**
```
✓ invoice with multiple products → post
  - 3 different products
  - Assert: all stock movements created
  - Assert: all avg_costs updated

✓ multi-item invoice with partial return
  - Invoice with items A, B, C → post
  - Return only item B → post return
  - Assert: stock for B reduced, A and C unchanged
  - Assert: avg_cost for B recalculated
```

---

### 3.3 Payment Flows (10 tests)

#### Test Class: `InvoicePaymentFlowTest`

**3.3.1 Subsequent Payments on Sales Invoice**
```
✓ single payment on credit invoice
  - Credit invoice 10000 → pay 3000
  - Assert: paid_amount = 3000, remaining = 7000
  - Assert: treasury +3000
  - Assert: partner balance -3000
  - Assert: payment record created

✓ multiple payments until full
  - Credit invoice 10000 → pay 3000, then 4000, then 3000
  - Assert: paid_amount = 10000, remaining = 0
  - Assert: fully_paid = true

✓ payment with settlement discount
  - Credit invoice 10000 → pay 2000 with discount 100
  - Assert: paid_amount = 2000, remaining = 7900
  - Assert: treasury +2000 (cash only)
  - Assert: partner balance -2100 (payment + discount)
  - Assert: 2 transactions created (collection + discount)
```

**3.3.2 Subsequent Payments on Purchase Invoice**
```
✓ single payment on credit invoice
  - Credit invoice 5000 → pay 1500
  - Assert: paid_amount = 1500, remaining = 3500
  - Assert: treasury -1500
  - Assert: partner balance -1500

✓ payment with settlement discount
  - Credit invoice 5000 → pay 1000 with discount 50
  - Assert: paid_amount = 1000, remaining = 3950
  - Assert: treasury -1000
  - Assert: partner balance -1050
```

**3.3.3 Overpayment Prevention**
```
✓ prevents payment exceeding remaining amount
  - Remaining 1000 → attempt pay 1500
  - Assert: exception or validation error

✓ allows exact payment
  - Remaining 1000 → pay 1000
  - Assert: success, remaining = 0
```

**3.3.4 Payment on Invoices with Installments**
```
✓ payment applies to installments via FIFO
  - Invoice with 3 installments of 1000 each
  - Pay 1500
  - Assert: first installment fully paid, second half paid
  - Assert: installment.paid_amount updated

✓ payment allocation recorded in installments
  - Pay installment → assert paid_amount on installment model updated

✓ full payment marks all installments paid
  - Pay total amount
  - Assert: all installments.paid_amount = amount
  - Assert: all installments status = 'paid'
```

---

### 3.4 Return Flows (8 tests)

#### Test Class: `ReturnFlowTest`

**3.4.1 Sales Return - Cash**
```
✓ cash sale → cash return → verify effects
  - Post cash sale 5000
  - Create cash return 1000 → post
  - Assert: stock increased by return quantity
  - Assert: treasury decreased by 1000 (refund)
  - Assert: COGS reversed on original invoice
  - Assert: partner balance unchanged (was cash)

✓ partial cash return
  - Invoice 5000 (3 items) → return 2 items
  - Assert: proportional COGS reversal
  - Assert: partial stock restoration
```

**3.4.2 Sales Return - Credit**
```
✓ credit sale → credit return → verify effects
  - Post credit sale 5000 (remaining 5000)
  - Create credit return 1000 → post
  - Assert: stock increased
  - Assert: NO treasury transaction
  - Assert: partner balance decreased by 1000 (debt reduced)
  - Assert: COGS reversed

✓ credit return on partially paid invoice
  - Invoice 5000, paid 2000 (remaining 3000)
  - Return 1000 (credit)
  - Assert: remaining decreased to 2000
  - Assert: partner balance decreased by 1000
```

**3.4.3 Purchase Return - Cash**
```
✓ cash purchase → cash return → verify effects
  - Post cash purchase 3000
  - Create cash return 500 → post
  - Assert: stock decreased by return quantity
  - Assert: treasury increased by 500 (refund from supplier)
  - Assert: avg_cost recalculated
```

**3.4.4 Purchase Return - Credit**
```
✓ credit purchase → credit return → verify effects
  - Post credit purchase 3000 (remaining 3000)
  - Create credit return 500 → post
  - Assert: stock decreased
  - Assert: NO treasury transaction
  - Assert: partner balance decreased by 500 (we owe less)
```

**3.4.5 Return Validation**
```
✓ prevents return exceeding invoice quantity
  - Invoice quantity 10 → attempt return 15
  - Assert: exception or validation error

✓ prevents return without stock (for purchase returns)
  - Stock 5, attempt return 10 → assert exception
```

---

### 3.5 Stock Flows (6 tests)

#### Test Class: `StockFlowTest`

**3.5.1 Purchase → Sell Flow**
```
✓ purchase → sell → verify stock and COGS
  - Purchase 100 @ 10 (avg_cost 10)
  - Sell 30 @ 20 (COGS = 300)
  - Assert: stock = 70
  - Assert: COGS on invoice = 300
  - Assert: gross profit = 600 - 300 = 300
```

**3.5.2 Multiple Purchases → Weighted Average**
```
✓ two purchases with different costs → sell
  - Purchase 10 @ 5 (cost 50)
  - Purchase 20 @ 8 (cost 160)
  - Avg = 210 / 30 = 7
  - Sell 15 → COGS = 15 * 7 = 105
  - Assert: avg_cost = 7.0000, COGS = 105.0000
```

**3.5.3 Stock Adjustments**
```
✓ adjustment_in increases stock
  - Opening balance adjustment +50
  - Assert: stock increased by 50

✓ adjustment_out decreases stock
  - Damage adjustment -10
  - Assert: stock decreased by 10
  - Assert: validates stock availability before subtraction
```

**3.5.4 Warehouse Transfers**
```
✓ transfer between warehouses
  - Transfer 20 from warehouse A to warehouse B
  - Assert: warehouse A stock -20
  - Assert: warehouse B stock +20
  - Assert: 2 stock movements created
```

**3.5.5 Overselling Prevention**
```
✓ prevents sale when stock insufficient
  - Stock 10 → attempt sell 20
  - Assert: exception during post
  - Assert: invoice remains draft, no stock movement created
```

---

### 3.6 Commission Flows (6 tests)

#### Test Class: `CommissionFlowTest`

**3.6.1 Commission Lifecycle**
```
✓ calculate → post invoice → pay commission
  - Invoice 10000, rate 5% → commission 500
  - Post invoice
  - Pay commission
  - Assert: commission_paid = true
  - Assert: treasury -500
  - Assert: transaction type COMMISSION_PAYOUT

✓ prevents paying commission on draft invoice
  - Draft invoice → attempt pay commission
  - Assert: exception

✓ prevents double payment
  - Pay commission → attempt pay again
  - Assert: exception
```

**3.6.2 Commission Reversal on Return**
```
✓ full return reverses full commission
  - Invoice 10000, commission 500
  - Full return 10000
  - Assert: commission_amount = 0
  - Assert: commission_paid = false
  - Assert: treasury +500 (reversal)

✓ partial return reverses proportional commission
  - Invoice 10000, commission 500
  - Return 2000 (20%)
  - Assert: commission_amount = 400 (500 - 100)
  - Assert: reversal transaction +100

✓ return after commission paid
  - Pay commission 500
  - Return 2000 (reversal 100)
  - Assert: commission_amount = 400
  - Assert: commission_paid still true (partially paid)
```

---

### 3.7 Capital Management Flows (8 tests)

#### Test Class: `CapitalManagementFlowTest`

**3.7.1 Period Closure and Profit Allocation**
```
✓ create period → calculate profit → allocate
  - 3 partners: 40%, 30%, 30%
  - Period profit 10000
  - Allocate
  - Assert: allocations = 4000, 3000, 3000
  - Assert: partner capitals increased
  - Assert: treasury transactions created

✓ handles zero profit
  - Period profit 0
  - Allocate
  - Assert: all allocations = 0

✓ handles negative profit (loss)
  - Period profit -5000
  - Allocate
  - Assert: allocations negative (capital reduced)
```

**3.7.2 Capital Injection**
```
✓ cash injection → auto-close period → recalculate percentages
  - Partner A injects 20000 cash
  - Assert: current period closed
  - Assert: partner capital increased by 20000
  - Assert: treasury +20000
  - Assert: all equity percentages recalculated
  - Assert: new period created with new percentages

✓ asset injection → no treasury impact
  - Partner injects asset 30000
  - Assert: capital increased
  - Assert: NO treasury transaction
  - Assert: transaction type ASSET_CONTRIBUTION

✓ multiple injections in sequence
  - Partner A injects, then Partner B injects
  - Assert: each closes period and recalculates
```

**3.7.3 Partner Drawings**
```
✓ drawing reduces capital and treasury
  - Partner capital 50000 → drawing 5000
  - Assert: capital = 45000
  - Assert: treasury -5000
  - Assert: tracked in period pivot

✓ validates drawing doesn't exceed capital
  - Capital 10000 → attempt drawing 15000
  - Assert: exception or validation (optional business rule)
```

---

### 3.8 Depreciation Flows (4 tests)

#### Test Class: `DepreciationFlowTest`

**3.8.1 Monthly Depreciation**
```
✓ asset purchased → monthly depreciation runs
  - Asset 120000, salvage 12000, life 10 years
  - Run monthly depreciation
  - Assert: accumulated_depreciation = 900
  - Assert: last_depreciation_date = current month
  - Assert: treasury transaction -900 (DEPRECIATION_EXPENSE)

✓ multiple months of depreciation
  - Run depreciation 12 times (1 year)
  - Assert: accumulated = 10800 (900 * 12)

✓ stops at full depreciation
  - Run depreciation for 120 months (10 years)
  - Assert: accumulated = 108000 (depreciable amount)
  - Assert: doesn't exceed depreciable amount
```

**3.8.2 Depreciation Scheduling**
```
✓ skips already depreciated month
  - Run depreciation twice in same month
  - Assert: second run skips asset, no change
```

---

## Phase 4: Data Integrity Tests

### 4.1 Transaction Atomicity Tests (8 tests)

#### Test Class: `TransactionAtomicityTest`

**4.1.1 Database Transactions**
```
✓ invoice posting rolls back on stock validation failure
  - Attempt post invoice with insufficient stock
  - Assert: DB::transaction rolls back
  - Assert: no stock movements created
  - Assert: no treasury transactions created
  - Assert: invoice status remains draft

✓ payment recording rolls back on treasury balance failure
  - Treasury balance 100 → attempt payment 200 (expense)
  - Assert: rollback
  - Assert: invoice paid_amount unchanged
  - Assert: no treasury transaction created

✓ return posting rolls back on error
  - Simulate error during return posting
  - Assert: stock unchanged
  - Assert: invoice COGS unchanged
  - Assert: return status remains draft
```

**4.1.2 Nested Transactions**
```
✓ handles nested transaction levels correctly
  - Post invoice (transaction level 1)
    - Inside: record treasury transaction (level 2)
  - Assert: no deadlock, correct behavior

✓ partial rollback in nested scenario
  - Mock scenario where inner transaction fails
  - Assert: outer transaction also rolls back
```

**4.1.3 Locking Tests**
```
✓ lockForUpdate prevents concurrent stock modifications
  - Simulate 2 concurrent sales of same product
  - Assert: one succeeds, other waits or fails properly

✓ lockForUpdate prevents concurrent treasury modifications
  - Simulate 2 concurrent treasury transactions
  - Assert: serialized execution, correct balance

✓ lockForUpdate on installments prevents race conditions
  - Simulate 2 concurrent payments on same installment
  - Assert: serialized, no overpayment
```

---

### 4.2 Immutability Tests (10 tests)

#### Test Class: `ImmutabilityTest`

**4.2.1 Posted Invoice Immutability**
```
✓ prevents changing partner_id on posted invoice
✓ prevents changing warehouse_id on posted invoice
✓ prevents changing items on posted invoice
✓ prevents changing total on posted invoice
✓ allows changing paid_amount on posted invoice
✓ allows changing remaining_amount on posted invoice
✓ allows changing commission_paid on posted invoice
```

**4.2.2 Installment Immutability**
```
✓ prevents changing amount on installment
✓ prevents changing due_date on installment
✓ allows changing paid_amount and status
```

**4.2.3 Audit Trail**
```
✓ soft deletes preserve treasury transaction history
  - Delete transaction → assert still in DB (deleted_at set)

✓ soft deletes preserve stock movement history
  - Delete movement → assert still queryable with withTrashed()
```

---

### 4.3 Referential Integrity Tests (10 tests)

#### Test Class: `ReferentialIntegrityTest`

**4.3.1 Foreign Key Constraints**
```
✓ prevents deleting warehouse with invoices
  - Create invoice → attempt delete warehouse
  - Assert: exception (foreign key constraint)

✓ prevents deleting partner with invoices
  - Create invoice → attempt delete partner
  - Assert: exception

✓ prevents deleting product with stock movements
  - Create movement → attempt delete product
  - Assert: exception

✓ prevents deleting treasury with transactions
  - Create transaction → attempt delete treasury
  - Assert: exception
```

**4.3.2 Set Null Behavior**
```
✓ sets created_by to null when user deleted
  - User creates invoice → delete user
  - Assert: invoice.created_by = null

✓ sets updated_by to null when user deleted
  - Similar test for updated_by
```

**4.3.3 Cascade Deletes**
```
✓ deleting invoice soft-deletes items
  - Soft delete invoice
  - Assert: items also soft deleted

✓ hard deleting invoice hard-deletes items
  - Force delete invoice
  - Assert: items permanently deleted
```

**4.3.4 Orphan Prevention**
```
✓ prevents creating invoice with non-existent partner
  - Attempt create invoice with partner_id 99999
  - Assert: exception

✓ prevents creating stock movement with non-existent product
  - Attempt create movement with product_id 99999
  - Assert: exception

✓ prevents creating transaction with non-existent treasury
  - Attempt create transaction with treasury_id 99999
  - Assert: exception
```

---

### 4.4 Validation Tests (12 tests)

#### Test Class: `ValidationTest`

**4.4.1 Stock Validation**
```
✓ prevents overselling
  - Stock 10 → attempt sell 20 → assert exception

✓ prevents stock adjustment out exceeding available
  - Stock 5 → damage 10 → assert exception

✓ allows selling exact stock
  - Stock 50 → sell 50 → assert success
```

**4.4.2 Treasury Validation**
```
✓ prevents negative balance
  - Balance 100 → withdraw 150 → assert exception

✓ allows withdrawal to zero
  - Balance 100 → withdraw 100 → assert success, balance 0
```

**4.4.3 Payment Validation**
```
✓ prevents payment exceeding remaining
  - Remaining 500 → pay 600 → assert exception

✓ prevents negative payment amount
  - Pay -100 → assert validation error
```

**4.4.4 Installment Validation**
```
✓ prevents installments on zero remaining
  - Fully paid invoice → attempt generate → assert exception

✓ equity percentages must sum to 100%
  - 3 partners: 40%, 30%, 20% → assert exception

✓ validates installment months > 0
  - Months = 0 or negative → assert exception
```

**4.4.5 Business Rule Validation**
```
✓ cash sale must have paid_amount = total
  - Cash invoice with paid_amount < total → assert validation

✓ credit sale must have remaining_amount = total - paid_amount
  - Assert formula enforced

✓ return quantity cannot exceed invoice quantity
  - Invoice qty 10 → return 15 → assert exception
```

---

### 4.5 Unique Constraint Tests (6 tests)

#### Test Class: `UniqueConstraintTest`

```
✓ prevents duplicate sales invoice numbers
  - Create invoice 'INV-001' → attempt create another 'INV-001'
  - Assert: exception

✓ prevents duplicate purchase invoice numbers
  - Similar test for purchase invoices

✓ prevents duplicate product barcodes
  - Create product barcode 'BC123' → attempt create another
  - Assert: exception

✓ prevents duplicate product SKUs
  - Similar test for SKUs

✓ prevents duplicate large barcodes
  - Similar test for large_barcode

✓ prevents duplicate partner names (if enforced)
  - Create partner 'ABC Corp' → attempt create another
  - Assert: exception or validation
```

---

## Phase 5: Calculation Accuracy Tests

### 5.1 BCMath Precision Tests (10 tests)

#### Test Class: `BCMathPrecisionTest`

**5.1.1 Financial Calculations**
```
✓ maintains 4 decimal precision in partner balance
  - Complex scenario with multiple decimals
  - Assert: balance has exactly 4 decimals

✓ maintains 4 decimal precision in treasury balance
  - Multiple transactions with decimals
  - Assert: balance = exact BCMath result

✓ maintains 4 decimal precision in COGS
  - Avg cost 12.3456, quantity 7
  - COGS = 86.4192
  - Assert: cost_total = 86.4192

✓ maintains 4 decimal precision in commission
  - Total 12345.67, rate 3.5%
  - Commission = 432.0985
  - Assert: commission_amount = 432.0985

✓ maintains 4 decimal precision in depreciation
  - Cost 123456.78, salvage 12345.67, life 8.5 years
  - Monthly = (111111.11) / 102 = 1089.3246
  - Assert: depreciation = 1089.3246
```

**5.1.2 Rounding Handling**
```
✓ installment rounding adjusts last installment
  - Total 1000, months 3
  - Expected: 333.33, 333.33, 333.34
  - Assert: SUM equals 1000.00 exactly

✓ weighted average rounding maintains precision
  - Purchase 7 @ 3.33, 11 @ 5.77
  - Avg = (23.31 + 63.47) / 18 = 4.8211
  - Assert: avg_cost = 4.8211

✓ discount percentage rounding
  - Subtotal 12345.67, discount 12.5%
  - Discount = 1543.2088
  - Assert: calculated_discount = 1543.2088
```

**5.1.3 Sum Validation**
```
✓ sum of installments equals invoice total
  - Generate installments
  - Assert: SUM(amount) = invoice.total (BCMath comparison)

✓ sum of allocated profit equals net profit
  - Allocate profit to partners
  - Assert: SUM(allocations) = net_profit

✓ sum of equity percentages equals 100%
  - Calculate percentages
  - Assert: SUM(equity_percentage) = 100.0000
```

---

### 5.2 Inventory Valuation Tests (8 tests)

#### Test Class: `InventoryValuationTest`

**5.2.1 Weighted Average Cost**
```
✓ simple weighted average (2 purchases)
  - Purchase 10 @ 5 = 50
  - Purchase 20 @ 8 = 160
  - Avg = 210 / 30 = 7.0000
  - Assert: product.avg_cost = 7.0000

✓ weighted average with 3+ purchases
  - Purchase 5 @ 10 = 50
  - Purchase 10 @ 12 = 120
  - Purchase 15 @ 8 = 120
  - Avg = 290 / 30 = 9.6667
  - Assert: avg_cost = 9.6667

✓ weighted average with return
  - Purchase 100 @ 10 = 1000
  - Return 20 @ 10 = 200
  - Avg from 80 units = 800 / 80 = 10.0000
  - Assert: avg_cost unchanged (same cost)

✓ weighted average updated after purchase return
  - Purchase 50 @ 8 = 400
  - Purchase 50 @ 12 = 600
  - Avg = 1000 / 100 = 10.0000
  - Return 30 @ 12 (from second purchase)
  - New avg = 640 / 70 = 9.1429
  - Assert: avg_cost = 9.1429
```

**5.2.2 Inventory Value Calculation**
```
✓ inventory value = avg_cost * stock
  - Avg cost 15.50, stock 200
  - Inventory value = 3100.00
  - Assert: calculateInventoryValue() = 3100.0000

✓ inventory value with date filtering (beginning)
  - Calculate inventory at start of month
  - Assert: only movements before date included

✓ inventory value with date filtering (ending)
  - Calculate inventory at end of month
  - Assert: all movements up to date included

✓ inventory value excludes negative stock products
  - Some products have negative stock (data error)
  - Assert: only positive stock included in valuation
```

---

### 5.3 COGS Accuracy Tests (8 tests)

#### Test Class: `COGSAccuracyTest`

**5.3.1 COGS Calculation at Sale**
```
✓ COGS uses avg_cost at time of sale
  - Avg cost 10.00 → sell 20 units → COGS 200.00
  - Change avg cost to 12.00 (new purchase)
  - Assert: original invoice COGS still 200.00 (not recalculated)

✓ multi-item COGS
  - Item A: qty 5, avg_cost 10 → COGS 50
  - Item B: qty 10, avg_cost 8 → COGS 80
  - Item C: qty 3, avg_cost 20 → COGS 60
  - Total COGS = 190
  - Assert: invoice.cost_total = 190.0000

✓ COGS with large unit sales
  - Product: factor 12
  - Avg cost 2 per piece (small unit)
  - Sell 3 large units → 36 pieces
  - COGS = 36 * 2 = 72
  - Assert: cost_total = 72.0000
```

**5.3.2 COGS Reversal on Return**
```
✓ full return reverses full COGS
  - Invoice COGS 500 → full return
  - Assert: invoice.cost_total = 0.0000

✓ partial return reverses proportional COGS
  - Invoice: item A qty 10, COGS 100
  - Return: item A qty 3
  - COGS reversal = 100 * (3 / 10) = 30
  - New COGS = 70
  - Assert: invoice.cost_total = 70.0000

✓ multi-item return reverses only returned items
  - Invoice: A COGS 100, B COGS 80, C COGS 60 (total 240)
  - Return: only item B (full qty)
  - New COGS = 240 - 80 = 160
  - Assert: cost_total = 160.0000
```

**5.3.3 COGS for Financial Reports**
```
✓ total COGS = SUM of cost_total from posted invoices
  - Create 10 invoices with various COGS
  - Assert: calculateCOGS() = SUM of all cost_total

✓ COGS respects date range filter
  - Invoices in January, February, March
  - Calculate COGS for February only
  - Assert: only February invoices included
```

---

### 5.4 Profit Calculation Tests (8 tests)

#### Test Class: `ProfitCalculationTest`

**5.4.1 Invoice-Level Profit**
```
✓ gross profit = total - COGS
  - Total 5000, COGS 3000 → gross = 2000
  - Assert: invoice.gross_profit = 2000.0000

✓ net profit = gross profit - commission
  - Gross 2000, commission 250 → net = 1750
  - Assert: invoice.net_profit = 1750.0000

✓ profit margin = (net profit / total) * 100
  - Net 1750, total 5000 → margin = 35%
  - Assert: invoice.profit_margin = 35.0000

✓ profit with discount
  - Subtotal 6000, discount 1000 → total 5000
  - COGS 3000, commission 250
  - Net profit = 5000 - 3000 - 250 = 1750
  - Assert: net_profit = 1750.0000
```

**5.4.2 Company-Level Profit (Financial Report)**
```
✓ net sales = total sales - returns
  - Sales 100000, returns 5000 → net sales = 95000
  - Assert: net_sales = 95000.0000

✓ gross profit = net sales - COGS
  - Net sales 95000, COGS 60000 → gross = 35000
  - Assert: gross_profit = 35000.0000

✓ operating expenses = expenses + commissions + discounts allowed
  - Expenses 10000, commissions 2000, discounts 500
  - OpEx = 12500
  - Assert: operating_expenses = 12500.0000

✓ net profit = gross - OpEx + other revenue + discounts received
  - Gross 35000, OpEx 12500, other revenue 2000, discounts received 300
  - Net = 35000 - 12500 + 2000 + 300 = 24800
  - Assert: net_profit = 24800.0000
```

---

### 5.5 Balance Calculation Tests (10 tests)

#### Test Class: `BalanceCalculationTest`

**5.5.1 Customer Balance**
```
✓ simple credit sale
  - Opening 0, credit sale 5000 → balance 5000
  - Assert: partner.balance = 5000.0000

✓ credit sale with collection
  - Opening 0, sale 5000, collection 2000 → balance 3000
  - Assert: balance = 3000.0000

✓ credit sale with return
  - Opening 0, sale 5000, return 1000 → balance 4000
  - Assert: balance = 4000.0000

✓ credit sale with settlement discount
  - Opening 0, sale 5000
  - Collection 2000 + discount 100 (total debt reduction 2100)
  - Balance = 5000 - 2100 = 2900
  - Assert: balance = 2900.0000

✓ complex customer scenario
  - Opening 1000
  - Credit sale 10000 (remaining 10000)
  - Cash sale 3000 (remaining 0, doesn't affect balance)
  - Collection 5000
  - Settlement discount 200
  - Credit return 1000
  - Balance = 1000 + 10000 - 5000 - 200 - 1000 = 4800
  - Assert: balance = 4800.0000
```

**5.5.2 Supplier Balance**
```
✓ simple credit purchase
  - Opening 0, credit purchase 3000 → balance 3000 (we owe)
  - Assert: partner.balance = 3000.0000

✓ credit purchase with payment
  - Opening 0, purchase 3000, payment 1000 → balance 2000
  - Assert: balance = 2000.0000

✓ credit purchase with settlement discount
  - Opening 0, purchase 5000
  - Payment 2000 + discount 100
  - Balance = 5000 - 2100 = 2900
  - Assert: balance = 2900.0000

✓ complex supplier scenario
  - Opening 2000
  - Credit purchase 8000
  - Cash purchase 1000 (doesn't affect balance)
  - Payment 3000
  - Settlement discount 150
  - Credit return 1000
  - Balance = 2000 + 8000 - 3000 - 150 - 1000 = 5850
  - Assert: balance = 5850.0000
```

**5.5.3 Shareholder Balance**
```
✓ shareholder capital transactions
  - Capital deposit 100000
  - Profit allocation 15000
  - Drawing 5000
  - Asset contribution 20000
  - Balance = 100000 + 15000 - 5000 + 20000 = 130000
  - Assert: partner.current_capital = 130000.0000
```

---

### 5.6 Treasury Balance Tests (6 tests)

#### Test Class: `TreasuryBalanceTest`

**5.6.1 Treasury Balance Calculation**
```
✓ simple balance = SUM of transactions
  - Collection 10000, payment -3000, expense -1000
  - Balance = 6000
  - Assert: getTreasuryBalance() = 6000.0000

✓ excludes discount transactions
  - Collection 5000, discount 200 (type='discount')
  - Balance = 5000 (discount excluded)
  - Assert: balance = 5000.0000

✓ complex scenario with all transaction types
  - Opening 0
  - Cash sale collection 10000
  - Cash purchase payment -3000
  - Subsequent collection 2000
  - Settlement discount 100 (excluded)
  - Expense -500
  - Salary -2000
  - Balance = 10000 - 3000 + 2000 - 500 - 2000 = 6500
  - Assert: balance = 6500.0000
```

**5.6.2 Multiple Treasuries**
```
✓ each treasury has separate balance
  - Treasury A: +5000, -1000 → balance 4000
  - Treasury B: +3000, -500 → balance 2500
  - Assert: getTreasuryBalance(A) = 4000, getTreasuryBalance(B) = 2500

✓ total balance across treasuries
  - Assert: SUM of all treasury balances = company cash
```

**5.6.3 Negative Balance Prevention**
```
✓ prevents transaction causing negative balance
  - Balance 1000 → attempt payment 1500
  - Assert: exception, balance remains 1000

✓ allows withdrawal to exactly zero
  - Balance 1000 → payment 1000
  - Assert: success, balance = 0
```

---

### 5.7 Commission Calculation Tests (5 tests)

#### Test Class: `CommissionCalculationTest`

```
✓ commission = total * rate / 100
  - Total 10000, rate 5% → commission 500
  - Assert: commission_amount = 500.0000

✓ commission with decimal rate
  - Total 12345.67, rate 3.5%
  - Commission = 432.0985
  - Assert: commission_amount = 432.0985

✓ commission reversal (proportional)
  - Invoice 10000, commission 500
  - Return 2000 (20%)
  - Reversal = 500 * 0.2 = 100
  - New commission = 400
  - Assert: commission_amount = 400.0000

✓ commission with discount
  - Subtotal 6000, discount 1000 → total 5000
  - Rate 5% → commission = 5000 * 0.05 = 250
  - Assert: commission calculated on total (after discount)

✓ total commissions paid (for reports)
  - 10 invoices with various commissions, some paid
  - Assert: calculateCommissionsPaid() = SUM where commission_paid = true
```

---

### 5.8 Depreciation Calculation Tests (5 tests)

#### Test Class: `DepreciationCalculationTest`

```
✓ straight-line monthly depreciation
  - Cost 120000, salvage 12000, life 10 years
  - Monthly = 108000 / 120 = 900
  - Assert: monthly = 900.0000

✓ depreciation with decimal life
  - Cost 50000, salvage 5000, life 7.5 years
  - Monthly = 45000 / 90 = 500
  - Assert: monthly = 500.0000

✓ accumulated depreciation over time
  - Monthly 900, run for 12 months
  - Accumulated = 10800
  - Assert: accumulated_depreciation = 10800.0000

✓ depreciation stops at depreciable amount
  - Depreciable 108000, monthly 900
  - Run for 125 months (more than life)
  - Assert: accumulated = 108000 (not 112500)

✓ book value calculation
  - Cost 120000, accumulated 36000
  - Book value = 84000
  - Assert: getBookValue() = 84000.0000
```

---

### 5.9 Equity Calculation Tests (5 tests)

#### Test Class: `EquityCalculationTest`

```
✓ equity percentage = (capital / total) * 100
  - 3 partners: 60000, 30000, 10000 (total 100000)
  - Percentages: 60%, 30%, 10%
  - Assert: each equity_percentage correct

✓ percentages sum to 100%
  - After recalculation
  - Assert: SUM(equity_percentage) = 100.0000 (BCMath)

✓ profit allocation by percentage
  - Profit 10000, partner 40%
  - Allocation = 4000
  - Assert: profit_allocated = 4000.0000

✓ capital recalculation after injection
  - 2 partners: A=50000, B=50000 (50% each)
  - A injects 20000
  - New capitals: A=70000, B=50000 (total 120000)
  - New percentages: A=58.3333%, B=41.6667%
  - Assert: percentages correct and sum to 100%

✓ period profit calculation
  - Revenue = Sales - Returns + Other
  - Expenses = Purchases - Returns + OpEx + Salaries + Depreciation + Commissions
  - Net Profit = Revenue - Expenses
  - Assert: net_profit matches manual calculation
```

---

## Phase 6: Concurrency Tests

### 6.1 Race Condition Tests (10 tests)

#### Test Class: `RaceConditionTest`

**6.1.1 Concurrent Sales**
```
✓ two sales of same product simultaneously
  - Stock 100, user A sells 60, user B sells 50 simultaneously
  - Expected: One succeeds, other fails (stock insufficient)
  - Assert: final stock = 40 or 50 (one transaction committed)

✓ concurrent sales with different products
  - Product A stock 50, product B stock 30
  - User A sells A, user B sells B simultaneously
  - Assert: both succeed, no conflict
```

**6.1.2 Concurrent Payments**
```
✓ two payments on same installment simultaneously
  - Installment amount 1000, remaining 1000
  - User A pays 600, user B pays 500 simultaneously
  - Expected: Both succeed if total <= 1000, or serialized
  - Assert: paid_amount <= 1000 (no overpayment)

✓ concurrent payments on same invoice
  - Invoice remaining 5000
  - Multiple users pay simultaneously
  - Assert: total paid <= invoice total
  - Assert: lockForUpdate serializes payments
```

**6.1.3 Concurrent Treasury Transactions**
```
✓ concurrent withdrawals
  - Balance 1000, user A withdraws 600, user B withdraws 500
  - Expected: One succeeds, other fails (insufficient balance)
  - Assert: balance never goes negative

✓ concurrent deposits
  - Balance 1000, user A deposits 500, user B deposits 300
  - Both succeed
  - Assert: final balance = 1800
```

**6.1.4 Concurrent Stock Adjustments**
```
✓ concurrent adjustments on same product
  - Stock 100
  - User A: damage -10, user B: gift -15 simultaneously
  - Assert: final stock = 75 (both applied correctly)

✓ concurrent purchase and sale
  - Stock 50
  - User A purchases +100, user B sells -30 simultaneously
  - Assert: final stock = 120 (both applied)
```

**6.1.5 Concurrent Invoice Posting**
```
✓ two users post same invoice simultaneously
  - Draft invoice
  - User A and B click "Post" at same time
  - Expected: One succeeds, other gets "already posted" error
  - Assert: stock movements created only once

✓ concurrent posting of different invoices (same product)
  - Product stock 100
  - Invoice A sells 60, Invoice B sells 50
  - Post simultaneously
  - Expected: One succeeds, other fails (stock insufficient)
  - Assert: no negative stock
```

---

### 6.2 Deadlock Prevention Tests (4 tests)

#### Test Class: `DeadlockPreventionTest`

```
✓ nested transactions with proper locking order
  - Outer: post invoice
  - Inner: record treasury transaction
  - Assert: no deadlock, both commit

✓ multiple lockForUpdate in consistent order
  - Transaction A: lock product then treasury
  - Transaction B: lock product then treasury (same order)
  - Assert: no deadlock

✓ avoid circular locking
  - Ensure application never locks resources in circular pattern
  - Assert: timeout test passes

✓ transaction timeout handling
  - Simulate long-running transaction
  - Assert: proper timeout and rollback
```

---

## Phase 7: Report Accuracy Tests

### 7.1 Partner Statement Tests (6 tests)

#### Test Class: `PartnerStatementTest`

```
✓ opening balance calculation
  - Transactions before date range
  - Assert: opening balance matches manual calculation

✓ transaction chronological order
  - Mixed invoices, payments, returns
  - Assert: sorted by date ascending

✓ running balance accuracy
  - Each transaction row has running balance
  - Assert: balance[i] = balance[i-1] + current_transaction

✓ debit/credit columns
  - Customer: sales = debit, payments = credit
  - Supplier: purchases = credit, payments = debit
  - Assert: correct column placement

✓ closing balance = opening + debit - credit
  - Assert: final balance matches partner.calculateBalance()

✓ date range filtering
  - Report from 2024-01-01 to 2024-01-31
  - Assert: only January transactions in body
  - Assert: opening includes all before Jan 1
```

---

### 7.2 Stock Card Tests (6 tests)

#### Test Class: `StockCardTest`

```
✓ opening stock calculation
  - Movements before date range
  - Assert: opening stock matches SUM of movements before start

✓ IN/OUT movement classification
  - Purchase, adjustment_in, return → IN
  - Sale, adjustment_out, return → OUT
  - Assert: correct classification

✓ running stock balance
  - Each movement row has running stock
  - Assert: stock[i] = stock[i-1] + movement_quantity

✓ reference document linking
  - Movement references invoice/adjustment/transfer
  - Assert: reference_type and reference_id populated

✓ closing stock = opening + IN - OUT
  - Assert: final stock matches product.getCurrentStock()

✓ date range filtering
  - Report from 2024-01-01 to 2024-01-31
  - Assert: only January movements in body
  - Assert: opening includes all before Jan 1
```

---

### 7.3 Financial Report Tests (12 tests)

#### Test Class: `FinancialReportTest`

**7.3.1 Income Statement Accuracy**
```
✓ total sales calculation
  - SUM all posted sales invoices
  - Assert: total_sales matches

✓ sales returns calculation
  - SUM all posted sales returns
  - Assert: sales_returns matches

✓ net sales = total sales - returns
  - Assert: net_sales correct

✓ COGS calculation
  - SUM of cost_total from sales invoices
  - Assert: cogs matches

✓ gross profit = net sales - COGS
  - Assert: gross_profit correct

✓ total purchases calculation
  - SUM all posted purchase invoices
  - Assert: total_purchases matches

✓ purchase returns calculation
  - SUM all posted purchase returns
  - Assert: purchase_returns matches

✓ operating expenses calculation
  - SUM expenses + commissions paid + discounts allowed
  - Assert: operating_expenses matches

✓ other revenue calculation
  - SUM revenues + discounts received
  - Assert: other_revenue matches

✓ net profit = gross profit - OpEx + other revenue
  - Assert: net_profit matches
```

**7.3.2 Balance Sheet Accuracy**
```
✓ fixed assets calculation
  - SUM(purchase_amount - accumulated_depreciation)
  - Assert: fixed_assets matches

✓ inventory valuation
  - SUM(avg_cost * stock) for positive stock
  - Assert: inventory matches

✓ debtors calculation
  - SUM of partner balances > 0 (non-shareholders)
  - Assert: debtors matches

✓ creditors calculation
  - ABS(SUM of partner balances < 0) (non-shareholders)
  - Assert: creditors matches

✓ cash calculation
  - SUM of treasury transactions (excluding discounts)
  - Assert: cash matches

✓ total assets = cash + inventory + debtors + fixed assets
  - Assert: total_assets correct

✓ shareholder equity calculation
  - SUM of current_capital for shareholders
  - Assert: shareholder_equity matches

✓ retained earnings = net profit - drawings
  - Assert: retained_earnings matches

✓ total equity and liabilities = equity + retained + creditors
  - Assert: total_liabilities_and_equity correct

✓ balance sheet balances
  - Assert: total_assets = total_liabilities_and_equity
```

**7.3.3 Date Range Filtering**
```
✓ income statement respects date range
  - Generate report for Q1 2024
  - Assert: only Q1 transactions included

✓ balance sheet uses point-in-time data
  - Generate balance sheet as of 2024-03-31
  - Assert: inventory, balances reflect date
```

---

### 7.4 Item Profitability Report Tests (4 tests)

#### Test Class: `ItemProfitabilityReportTest`

```
✓ calculates profit per product
  - Product A: sales 10000, COGS 6000 → profit 4000
  - Assert: profit_amount = 4000.0000

✓ calculates profit margin per product
  - Profit 4000, sales 10000 → margin 40%
  - Assert: profit_margin = 40.0000

✓ aggregates across multiple invoices
  - Product A sold in 5 invoices
  - Assert: total profit = SUM of all profits

✓ includes commission in net profit
  - Sales 10000, COGS 6000, commission 500
  - Net profit = 3500
  - Assert: net_profit = 3500.0000
```

---

## Testing Checklist

### Phase 1: Service Layer Tests
- [ ] InstallmentService (15 tests)
- [ ] TreasuryService (25 tests)
- [ ] StockService (20 tests)
- [ ] CapitalService (15 tests)
- [ ] DepreciationService (8 tests)
- [ ] CommissionService (10 tests)
- [ ] ReportService (12 tests)
- [ ] FinancialReportService (15 tests)

**Total: 120 tests**

---

### Phase 2: Model Business Logic Tests
- [ ] Partner Model (20 tests)
- [ ] SalesInvoice Model (15 tests)
- [ ] PurchaseInvoice Model (15 tests)
- [ ] Product Model (10 tests)
- [ ] Installment Model (8 tests)
- [ ] FixedAsset Model (6 tests)
- [ ] TreasuryTransaction Model (4 tests)
- [ ] StockMovement Model (4 tests)

**Total: 82 tests**

---

### Phase 3: Integration Tests
- [ ] Sales Invoice Lifecycle (10 tests)
- [ ] Purchase Invoice Lifecycle (10 tests)
- [ ] Payment Flows (10 tests)
- [ ] Return Flows (8 tests)
- [ ] Stock Flows (6 tests)
- [ ] Commission Flows (6 tests)
- [ ] Capital Management Flows (8 tests)
- [ ] Depreciation Flows (4 tests)

**Total: 62 tests**

---

### Phase 4: Data Integrity Tests
- [ ] Transaction Atomicity (8 tests)
- [ ] Immutability (10 tests)
- [ ] Referential Integrity (10 tests)
- [ ] Validation (12 tests)
- [ ] Unique Constraints (6 tests)

**Total: 46 tests**

---

### Phase 5: Calculation Accuracy Tests
- [ ] BCMath Precision (10 tests)
- [ ] Inventory Valuation (8 tests)
- [ ] COGS Accuracy (8 tests)
- [ ] Profit Calculation (8 tests)
- [ ] Balance Calculation (10 tests)
- [ ] Treasury Balance (6 tests)
- [ ] Commission Calculation (5 tests)
- [ ] Depreciation Calculation (5 tests)
- [ ] Equity Calculation (5 tests)

**Total: 65 tests**

---

### Phase 6: Concurrency Tests
- [ ] Race Condition Tests (10 tests)
- [ ] Deadlock Prevention (4 tests)

**Total: 14 tests**

---

### Phase 7: Report Accuracy Tests
- [ ] Partner Statement (6 tests)
- [ ] Stock Card (6 tests)
- [ ] Financial Report (12 tests)
- [ ] Item Profitability Report (4 tests)

**Total: 28 tests**

---

## Grand Total: 417 Tests

---

## Test Execution Plan

### Priority Levels

**P0 - Critical (Must Run Before Production)**
- All financial calculation tests (Phase 5)
- All data integrity tests (Phase 4)
- Core integration tests (Phase 3: invoices, payments, returns)

**P1 - High Priority (Before Major Releases)**
- All service layer tests (Phase 1)
- All model business logic tests (Phase 2)
- Report accuracy tests (Phase 7)

**P2 - Standard (Regular CI/CD)**
- Concurrency tests (Phase 6)
- Edge case tests

### Continuous Integration

**On Every Commit:**
- Run P0 tests (~200 tests, ~5-10 minutes)

**On Pull Request:**
- Run P0 + P1 tests (~370 tests, ~10-20 minutes)

**Nightly:**
- Run all tests including concurrency (417 tests, ~30-60 minutes)

---

## Custom Assertions

### BCMath Assertions
```php
// Custom assertion for BCMath comparison
protected function assertBCEquals($expected, $actual, $scale = 4) {
    $this->assertEquals(
        0,
        bccomp($expected, $actual, $scale),
        "Expected {$expected} but got {$actual}"
    );
}

// Assert sum equals
protected function assertBCSumEquals($expected, array $values, $scale = 4) {
    $sum = array_reduce($values, fn($carry, $val) => bcadd($carry, $val, $scale), '0');
    $this->assertBCEquals($expected, $sum, $scale);
}
```

### Money Assertions
```php
// Assert money value with 4 decimals
protected function assertMoney($expected, $actual) {
    $this->assertBCEquals($expected, $actual, 4);
}

// Assert percentage with 4 decimals
protected function assertPercentage($expected, $actual) {
    $this->assertBCEquals($expected, $actual, 4);
}
```

---

## Test Data Factories

### Key Factories Needed
```php
// ProductFactory - with avg_cost, stock, units
// PartnerFactory - customer, supplier, shareholder types
// SalesInvoiceFactory - draft, posted, cash, credit
// PurchaseInvoiceFactory - draft, posted, cash, credit
// StockMovementFactory - various types
// TreasuryTransactionFactory - various types
// InstallmentFactory - with dates, amounts
// FixedAssetFactory - with depreciation data
```

---

## Performance Benchmarks

### Target Execution Times
- Unit test: < 50ms each
- Integration test: < 200ms each
- Concurrency test: < 500ms each
- Report test: < 1s each

### Optimization Tips
- Use in-memory SQLite for speed
- Seed minimal data per test
- Use database transactions for isolation
- Mock external dependencies

---

## Test Coverage Goals

### Minimum Coverage Targets
- **Services**: 95%+ line coverage
- **Models**: 90%+ line coverage
- **Critical calculations**: 100% branch coverage
- **Overall**: 85%+ code coverage

---

## Conclusion

This comprehensive test plan covers:
- **417 total tests** across 7 phases
- **100% coverage** of business logic and calculations
- **Focus areas**: Financial precision, data integrity, concurrency
- **BCMath validation** for all money calculations
- **Integration testing** for complex workflows
- **Concurrency testing** for race conditions

Execute tests in priority order (P0 → P1 → P2) and aim for 85%+ overall code coverage with 100% coverage on critical financial calculations.
