# Dynamic Capital Management - Testing Guide

## 🎯 Where to See It in Filament

After running the test seeder, you'll find the **Capital Management** features in your Filament admin panel:

### Navigation Menu
Look for a new navigation group: **"إدارة رأس المال"** (Capital Management)

Under it, you'll see:
- **"فترات رأس المال"** (Equity Periods) - View and manage capital periods

---

## 🚀 Quick Start - Run Test Data

### Step 1: Run the Seeder

```bash
php artisan db:seed --class=CapitalManagementTestSeeder
```

This will create:
- ✅ 2 shareholders (Partner A: 60%, Partner B: 40%)
- ✅ Initial capital transactions (150,000 + 100,000)
- ✅ First equity period (Period #1 - مفتوحة/Open)

### Step 2: View in Filament

1. Open your browser and go to your Filament admin panel
2. Look in the sidebar for **"إدارة رأس المال"**
3. Click on **"فترات رأس المال"**
4. You should see **Period #1** with status "مفتوحة" (Open)

### Step 3: View Period Details

Click on Period #1 to see:
- Period number and dates
- Partner percentages (محمد: 60%, أحمد: 40%)
- Capital at start for each partner
- Financial summary (when closed)

---

## 🧪 Testing Scenarios

### Test 1: View Initial Setup

**In Filament:**
1. Go to "فترات رأس المال" (Equity Periods)
2. See Period #1 (Open)
3. Click to view details
4. See both partners with their locked percentages

**Expected Result:**
- Period #1 shown
- Partner A: 60% - 150,000 ج.م
- Partner B: 40% - 100,000 ج.م
- Status: مفتوحة (Open)

---

### Test 2: Capital Injection (Changes Percentages)

**Via Tinker:**

```bash
php artisan tinker
```

```php
// Get the capital service
$capitalService = app(\App\Services\CapitalService::class);

// Get Partner A
$partnerA = Partner::where('name', 'LIKE', '%محمد%')->first();

// Inject 50,000 more capital
$capitalService->injectCapital($partnerA, 50000, 'cash', [
    'description' => 'Additional capital investment - Test'
]);

// Check new percentages
$partnerA->fresh()->equity_percentage; // Should be ~66.67%
Partner::where('name', 'LIKE', '%أحمد%')->first()->equity_percentage; // Should be ~33.33%

// Check periods
\App\Models\EquityPeriod::all(); // Should see 2 periods now
```

**Expected Result:**
- Old period (Period #1) automatically closed
- Profit calculated and allocated (if any)
- New period (Period #2) created
- Partner A: 66.67% (200,000 / 300,000)
- Partner B: 33.33% (100,000 / 300,000)

**In Filament:**
1. Refresh "فترات رأس المال" page
2. See Period #1 (مغلقة/Closed)
3. See Period #2 (مفتوحة/Open)
4. View Period #2 details - see new percentages

---

### Test 3: Close Period Manually

**In Filament:**
1. Go to "فترات رأس المال"
2. Find the open period
3. Click the **"إغلاق الفترة"** (Close Period) action
4. Select end date (today)
5. Add notes (optional)
6. Confirm

**Expected Result:**
- Period status changes to "مغلقة" (Closed)
- Net profit calculated
- Profit allocated to partners
- Capital balances updated

---

### Test 4: View Capital Ledger for a Partner

**Via Tinker:**

```php
$partner = Partner::where('name', 'LIKE', '%محمد%')->first();

// Get capital ledger
$ledger = $partner->getCapitalLedger();

// Display
foreach ($ledger as $transaction) {
    echo $transaction->created_at->format('Y-m-d') . " | ";
    echo \App\Enums\TransactionType::from($transaction->type)->getLabel() . " | ";
    echo number_format($transaction->amount, 2) . " ج.م\n";
}
```

**Expected Output:**
```
2026-01-17 | إضافة رأس مال | 50,000.00 ج.م
2026-01-17 | توزيع أرباح | XXX.XX ج.م
2026-01-17 | إيداع رأس المال | 150,000.00 ج.م
```

---

### Test 5: Manager Salary (Reduces Profit)

**Via Tinker:**

```php
// Get Partner B (the manager)
$partnerB = Partner::where('is_manager', true)->first();

// Get treasury
$treasury = Treasury::where('type', 'cash')->first();

// Record salary payment (this is an EXPENSE, not capital withdrawal)
$treasuryService = app(\App\Services\TreasuryService::class);
$treasuryService->recordTransaction(
    treasury_id: $treasury->id,
    type: 'salary_payment',
    amount: -12000, // Negative = expense
    description: 'Salary for ' . now()->format('M Y'),
    employee_id: null, // Link to user if partner has a user account
    partner_id: $partnerB->id // Optional: for tracking
);

// Now close the period to see salary as expense
$capitalService = app(\App\Services\CapitalService::class);
$period = $capitalService->getCurrentPeriod();

if ($period) {
    $profit = $capitalService->calculatePeriodProfit($period);
    echo "Net Profit: " . number_format($profit, 2) . " ج.م\n";
    // Profit will be reduced by the 12,000 salary
}
```

**Expected Result:**
- Salary recorded as expense
- Net profit reduced by 12,000
- When period closes, BOTH partners share the reduced profit proportionally
- Partner B's capital does NOT decrease (salary is company expense, not drawing)

---

### Test 6: Asset Contribution (The Car Example)

**Via Tinker:**

```php
// Partner B contributes a car worth 200,000
$partnerB = Partner::where('is_manager', true)->first();

// Create the fixed asset
$asset = \App\Models\FixedAsset::create([
    'name' => 'سيارة - مساهمة من الشريك B',
    'description' => 'Toyota Corolla 2024',
    'purchase_amount' => 200000,
    'purchase_date' => now(),
    'funding_method' => 'equity', // Important!
    'treasury_id' => Treasury::where('type', 'cash')->first()->id,
    'status' => 'active',
    'useful_life_years' => 5,
    'salvage_value' => 50000,
    'is_contributed_asset' => true,
    'contributing_partner_id' => $partnerB->id,
]);

// Now inject this as capital
$capitalService = app(\App\Services\CapitalService::class);
$capitalService->injectCapital($partnerB, 200000, 'asset', [
    'description' => 'Asset contribution: ' . $asset->name,
    'reference_type' => \App\Models\FixedAsset::class,
    'reference_id' => $asset->id,
]);

// Check new percentages
Partner::shareholders()->get()->each(function ($p) {
    echo $p->name . ": " . number_format($p->equity_percentage, 2) . "%\n";
});
```

**Expected Result:**
- Partner B's capital increases by 200,000
- New period created with updated percentages
- Asset tracked with depreciation info
- Monthly depreciation: (200,000 - 50,000) / 60 = 2,500 per month

---

## 📊 What You Should See in Filament

### Equity Periods List Page

| Period # | From | To | Net Profit | Status | Closed By | Closed At |
|----------|------|----|-----------
|--------|-----------|-----------|
| 2 | 2026-01-17 | — | — | مفتوحة | — | — |
| 1 | 2025-10-17 | 2026-01-17 | 0.00 ج.م | مغلقة | Admin | 2026-01-17 12:00 |

### Period View Page

**معلومات الفترة (Period Info):**
- رقم الفترة: #2
- تاريخ البداية: 2026-01-17
- الحالة: مفتوحة

**نسب الشركاء (Partner Percentages):**
| Partner | Equity % | Capital at Start | Profit Allocated |
|---------|----------|------------------|------------------|
| محمد أحمد | 66.67% | 200,000.00 ج.م | 0.00 ج.م |
| أحمد علي | 33.33% | 100,000.00 ج.م | 0.00 ج.م |

---

## 🎓 Understanding the Flow

### Normal Operation Flow:

```
1. Business operates with Period #1 (60/40 split)
   ↓
2. Revenue & Expenses accumulate
   ↓
3. Partner A wants to inject 50,000 more
   ↓
4. System automatically:
   - Calculates profit since period start
   - Allocates 60% to A, 40% to B
   - Closes Period #1
   - Records the 50,000 capital
   - Recalculates: A=66.67%, B=33.33%
   - Creates Period #2 with new split
   ↓
5. Business continues with Period #2 (66.67/33.33 split)
```

### Manager Salary Flow:

```
Manager (Partner B) receives 12,000 salary
   ↓
Recorded as SALARY_PAYMENT (expense)
   ↓
Reduces company profit (affects BOTH partners)
   ↓
NOT a capital withdrawal
   ↓
Partner B's capital unchanged
```

### Asset Contribution Flow:

```
Partner B contributes car worth 200,000
   ↓
Created as Fixed Asset with depreciation settings
   ↓
Recorded as ASSET_CONTRIBUTION
   ↓
Partner B's capital increases by 200,000
   ↓
Percentages recalculated
   ↓
Monthly: 2,500 depreciation recorded
   ↓
Depreciation reduces profit for ALL partners
```

---

## 🔍 Verification Checklist

After running tests, verify:

- [ ] Equity periods appear in Filament
- [ ] Partner percentages shown correctly
- [ ] Capital injection creates new period
- [ ] Old period auto-closes on capital injection
- [ ] Percentages recalculate correctly
- [ ] Manager salary is an expense (not drawing)
- [ ] Capital ledger shows all transactions
- [ ] Asset contribution tracked properly
- [ ] Period closure calculates profit

---

## 🆘 Troubleshooting

**Issue:** "No periods showing in Filament"
- **Solution:** Run the seeder: `php artisan db:seed --class=CapitalManagementTestSeeder`

**Issue:** "Navigation menu not showing 'إدارة رأس المال'"
- **Solution:** Clear cache: `php artisan filament:cache-components`

**Issue:** "Error when injecting capital"
- **Solution:** Make sure you have a cash treasury: `Treasury::where('type', 'cash')->first()`

**Issue:** "Percentages don't add up to 100%"
- **Solution:** This is due to rounding. The system uses bcmath for precision, slight differences (0.01%) are normal.

---

**Happy Testing! 🎉**

Your Dynamic Capital Management system is ready to use!
