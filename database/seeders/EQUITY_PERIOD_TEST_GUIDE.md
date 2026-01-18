# Equity Period Comprehensive Test Guide

## Overview
This guide explains the comprehensive test suite for the equity period system, covering positive tests, negative tests, and edge cases.

## Prerequisites
```bash
# 1. Clean database
php artisan migrate:fresh

# 2. Run dummy data seeder (creates base data)
php artisan db:seed --class=DummyDataSeeder

# 3. Run the equity period test seeder
php artisan db:seed --class=EquityPeriodTestSeeder
```

## Test Scenarios

### Scenario 1: Two Equity Periods on the Same Day

**Objective**: Verify that multiple capital injections on the same day create separate periods with correct equity calculations.

**Steps**:
1. **9:00 AM** - Partner 1 adds 60,000 capital
   - Auto-creates Period 1
   - Partner 1 has 100% equity
   - Period 1 status: `open`

2. **2:00 PM** - Partner 2 adds 40,000 capital
   - Auto-closes Period 1
   - Creates Period 2
   - Partner 1: 60% equity (60k / 100k)
   - Partner 2: 40% equity (40k / 100k)
   - Period 2 status: `open`

3. **3:00 PM** - Add financial transactions
   - Revenue: 50,000 (30k service + 20k products)
   - Expenses: 20,000 (10k rent + 10k utilities)

4. **11:00 PM** - Close Period 2
   - Expected Net Profit: 30,000 (50k - 20k)
   - Partner 1 profit: 18,000 (60% of 30k)
   - Partner 2 profit: 12,000 (40% of 30k)
   - Final capitals:
     - Partner 1: 78,000 (60k + 18k)
     - Partner 2: 52,000 (40k + 12k)

**Validations**:
- ✅ Period numbers are sequential (1, 2, 3)
- ✅ Equity percentages sum to 100%
- ✅ `equity_period_partners` table has correct locked percentages
- ✅ `profit_allocated` matches calculated profit shares
- ✅ `treasury_transactions` has all capital deposits, revenues, expenses, and profit allocations
- ✅ Treasury balance: 130,000 (60k + 40k + 50k - 20k)
- ✅ Partner capitals updated correctly

---

### Scenario 2: Equity Periods on Different Days

**Objective**: Test multi-day operations with period closures and profit allocations.

**Day 1**:
- Partner 1 invests 100,000
- Period 1 created (Partner 1: 100% equity)
- Revenue: 15,000
- Expense: 5,000
- Net Profit: 10,000

**Day 2**:
- Partner 2 invests 50,000
- Period 1 auto-closes and allocates 10,000 profit to Partner 1
- Partner 1 new capital: 110,000
- Period 2 created:
  - Partner 1: 68.75% equity (110k / 160k)
  - Partner 2: 31.25% equity (50k / 160k)
- Revenue: 20,000
- Expense: 8,000

**Day 3**:
- Close Period 2
- Net Profit: 12,000 (20k - 8k)
- Partner 1 profit: 8,250 (68.75% of 12k)
- Partner 2 profit: 3,750 (31.25% of 12k)
- Final capitals:
  - Partner 1: 118,250 (110k + 8,250)
  - Partner 2: 53,750 (50k + 3,750)

**Validations**:
- ✅ Period 1 closed with correct profit calculation
- ✅ Period 2 has updated equity percentages
- ✅ Profit allocated proportionally based on locked percentages
- ✅ Treasury balance: 172,000 (100k + 15k - 5k + 50k + 20k - 8k)
- ✅ All transactions tracked correctly

---

### Scenario 3: Edge Cases & Negative Tests

#### Edge Case 1: Zero Profit Period
**Setup**:
- Partner invests 50,000
- Revenue: 10,000
- Expense: 10,000
- Net Profit: 0

**Expected**:
- ✅ Period closes without error
- ✅ Partner capital remains unchanged (50,000)
- ✅ `net_profit` = 0
- ✅ No profit allocation transactions created

---

#### Edge Case 2: Negative Profit (Loss)
**Setup**:
- Partner invests 80,000
- Revenue: 5,000
- Expense: 15,000
- Net Loss: -10,000

**Expected**:
- ✅ Period closes with negative profit
- ✅ Partner capital decreases to 70,000 (80k - 10k)
- ✅ `net_profit` = -10,000
- ✅ Profit allocation transaction shows negative amount (-10,000)

---

#### Edge Case 3: Partner Drawing
**Setup**:
- Partner invests 100,000
- Partner takes drawing of 20,000

**Expected**:
- ✅ Partner capital reduces to 80,000
- ✅ Treasury balance reduces by 20,000
- ✅ `partner_drawing` transaction created with amount -20,000
- ✅ `equity_period_partners.drawings_taken` = 20,000
- ✅ Equity percentages remain unchanged (drawings don't affect ownership)

---

#### Edge Case 4: Decimal Precision
**Setup**:
- Partner 1 invests 33,333.33
- Partner 2 invests 66,666.67
- Total: 100,000
- Revenue: 999.99

**Expected**:
- ✅ Equity percentages calculated correctly:
  - Partner 1: 33.3333%
  - Partner 2: 66.6667%
- ✅ Profit allocated with decimal precision:
  - Partner 1: 333.33 (33.3333% of 999.99)
  - Partner 2: 666.66 (66.6667% of 999.99)
- ✅ Final capitals:
  - Partner 1: 33,666.66
  - Partner 2: 67,333.33
- ✅ No rounding errors exceed 1 unit

---

## Database Tables Verification

### `equity_periods` Table
```sql
SELECT
    period_number,
    start_date,
    end_date,
    status,
    total_revenue,
    total_expenses,
    net_profit,
    closed_at
FROM equity_periods
ORDER BY period_number;
```

**Expected Columns**:
- `period_number`: Sequential (1, 2, 3...)
- `start_date`: Timestamp of period start
- `end_date`: Timestamp when closed (NULL if open)
- `status`: 'open' or 'closed'
- `total_revenue`: Sum of all revenues in period
- `total_expenses`: Sum of all expenses in period
- `net_profit`: total_revenue - total_expenses
- `closed_at`: Timestamp when closed

---

### `equity_period_partners` Table
```sql
SELECT
    ep.period_number,
    p.name as partner_name,
    epp.equity_percentage,
    epp.capital_at_start,
    epp.profit_allocated,
    epp.capital_injected,
    epp.drawings_taken
FROM equity_period_partners epp
JOIN equity_periods ep ON epp.equity_period_id = ep.id
JOIN partners p ON epp.partner_id = p.id
ORDER BY ep.period_number, p.name;
```

**Expected Columns**:
- `equity_percentage`: Locked percentage for this partner in this period
- `capital_at_start`: Partner's capital when period started
- `profit_allocated`: Profit given to partner when period closed
- `capital_injected`: Additional capital added during period
- `drawings_taken`: Amount withdrawn during period

**Validations**:
- ✅ Sum of `equity_percentage` per period = 100%
- ✅ `profit_allocated` = `net_profit` × (`equity_percentage` / 100)

---

### `partners` Table
```sql
SELECT
    name,
    type,
    current_capital,
    equity_percentage
FROM partners
WHERE type = 'shareholder'
ORDER BY name;
```

**Expected Columns**:
- `current_capital`: Current total capital (includes all injections and profit allocations, minus drawings)
- `equity_percentage`: Current ownership percentage (recalculated after each capital injection)

**Validations**:
- ✅ Sum of all shareholders' `equity_percentage` = 100%
- ✅ `current_capital` = Sum of all capital transactions for this partner

---

### `treasury_transactions` Table
```sql
SELECT
    type,
    amount,
    description,
    partner_id,
    reference_type,
    created_at
FROM treasury_transactions
WHERE type IN ('capital_deposit', 'profit_allocation', 'partner_drawing', 'income', 'expense')
ORDER BY created_at;
```

**Transaction Types**:
1. **`capital_deposit`**: Partner adds capital (positive amount)
2. **`profit_allocation`**: Profit distributed to partner (positive amount)
3. **`partner_drawing`**: Partner withdraws money (negative amount)
4. **`income`**: Revenue posted (positive amount)
5. **`expense`**: Expense posted (negative amount)

**Validations**:
- ✅ Treasury balance = Sum of all transaction amounts (excluding 'discount' type)
- ✅ Each capital injection has corresponding `capital_deposit` transaction
- ✅ Each period closure creates `profit_allocation` transactions for all partners
- ✅ All amounts are correctly signed (positive for inflows, negative for outflows)

---

## Manual Verification Queries

### 1. Verify Total Capital Equals Sum of Transactions
```sql
SELECT
    p.name,
    p.current_capital,
    SUM(tt.amount) as transaction_total
FROM partners p
LEFT JOIN treasury_transactions tt ON tt.partner_id = p.id
WHERE p.type = 'shareholder'
  AND tt.type IN ('capital_deposit', 'profit_allocation', 'partner_drawing')
GROUP BY p.id, p.name, p.current_capital;
```
**Expected**: `current_capital` should equal `transaction_total` for each partner.

---

### 2. Verify Period Profit Calculations
```sql
SELECT
    period_number,
    total_revenue,
    total_expenses,
    net_profit,
    (total_revenue - total_expenses) as calculated_profit,
    CASE
        WHEN net_profit = (total_revenue - total_expenses) THEN 'CORRECT'
        ELSE 'ERROR'
    END as validation
FROM equity_periods
ORDER BY period_number;
```
**Expected**: All rows should show `validation = 'CORRECT'`.

---

### 3. Verify Profit Allocation Distribution
```sql
SELECT
    ep.period_number,
    ep.net_profit as total_profit,
    SUM(epp.profit_allocated) as distributed_profit,
    ABS(ep.net_profit - SUM(epp.profit_allocated)) as difference
FROM equity_periods ep
JOIN equity_period_partners epp ON epp.equity_period_id = ep.id
WHERE ep.status = 'closed'
GROUP BY ep.id, ep.period_number, ep.net_profit;
```
**Expected**: `difference` should be less than 0.01 for all periods.

---

### 4. Verify Treasury Balance
```sql
SELECT
    SUM(CASE WHEN type != 'discount' THEN amount ELSE 0 END) as treasury_balance
FROM treasury_transactions;
```
**Expected**: Should match the sum of all capital deposits + revenues - expenses - drawings.

---

## Common Issues & Troubleshooting

### Issue 1: Percentages Don't Sum to 100%
**Cause**: Floating point rounding errors
**Solution**: Use `DECIMAL(10,4)` data type and `bcmath` functions in PHP

---

### Issue 2: Treasury Balance Mismatch
**Cause**: Missing transactions or incorrect amount signs
**Solution**:
- Check all transaction types are recorded
- Verify positive/negative amounts:
  - Deposits: positive
  - Withdrawals: negative
  - Revenues: positive
  - Expenses: negative

---

### Issue 3: Profit Not Allocated
**Cause**: Period not properly closed
**Solution**: Ensure `closePeriodAndAllocate()` is called, not just `close()`

---

### Issue 4: Multiple Open Periods
**Cause**: Auto-close logic not triggered
**Solution**: Capital injection should auto-close existing open period before creating new one

---

## Expected Test Output

When running the seeder, you should see:
```
====================================
EQUITY PERIOD COMPREHENSIVE TEST
====================================

Initializing services and base data...
✓ Services initialized

### SCENARIO 1: Two Equity Periods on the Same Day ###

--- Starting Same Day Test ---
✓ Created partners: Shareholder A, Shareholder B
✓ Partner 1 capital injection verified (Period 1 created)
✓ Partner 2 capital injection verified (Period 1 closed, Period 2 created)
✓ Added revenues (50,000) and expenses (20,000)
✓ Period closed and profit allocated correctly
✓✓ SCENARIO 1 PASSED: Two periods on same day

### SCENARIO 2: Equity Periods on Different Days ###

--- Starting Different Days Test ---
✓ Day 1: Partner 1 invested 100,000
✓ Day 2: Partner 2 invested 50,000, Period 1 closed with 10,000 profit
✓✓ SCENARIO 2 PASSED: Multiple periods on different days

### SCENARIO 3: Edge Cases & Negative Tests ###

--- Starting Edge Cases Test ---

Edge Case 1: Zero profit period
✓ Edge Case 1 passed: Zero profit handled correctly

Edge Case 2: Negative profit (loss)
✓ Edge Case 2 passed: Negative profit (loss) handled correctly

Edge Case 3: Partner drawing
✓ Edge Case 3 passed: Partner drawing recorded correctly

Edge Case 4: Decimal precision
✓ Edge Case 4 passed: Decimal precision handled correctly
✓✓ SCENARIO 3 PASSED: All edge cases handled correctly

✅ ALL TESTS PASSED SUCCESSFULLY!
```

---

## Summary

This comprehensive test suite validates:
1. ✅ Equity period creation and closure
2. ✅ Capital injection and equity percentage calculation
3. ✅ Revenue and expense tracking
4. ✅ Profit/loss calculation and allocation
5. ✅ Partner drawing functionality
6. ✅ Treasury transaction integrity
7. ✅ Multi-day operations
8. ✅ Edge cases (zero profit, losses, decimals)
9. ✅ Database consistency across all related tables

All test scenarios are isolated and can be run independently by commenting out others in the `run()` method.
