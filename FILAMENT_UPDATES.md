# Filament Resources - Capital Management Updates

## Summary of Changes

All Filament resources have been updated to include the new capital management and depreciation fields.

---

## 1. PartnerResource (العملاء والموردين)

### Form - New Section: "معلومات رأس المال"
**Visible only for shareholders (type = 'shareholder')**

Fields added:
- **current_capital** - رأس المال الحالي (Read-only, calculated)
- **equity_percentage** - نسبة الملكية % (Read-only, calculated)
- **is_manager** - شريك مدير؟ (Toggle, editable)
- **monthly_salary** - الراتب الشهري (Visible when is_manager = true)

### Table - New Columns
All columns are **toggleable** (can be shown/hidden via column selector):

- **current_balance** - الرصيد الجاري (Always visible, renamed from "الرصيد")
- **current_capital** - رأس المال (Hidden by default, shows capital amount)
- **equity_percentage** - نسبة الملكية (Hidden by default, shows percentage)
- **is_manager** - مدير (Hidden by default, boolean icon)

### How to View:
1. Go to `/admin/partners`
2. Filter by type: "شريك (مساهم)"
3. Click the column selector (top right)
4. Enable: "رأس المال", "نسبة الملكية", "مدير"
5. Edit any shareholder to see the full capital section

---

## 2. EquityPeriodResource (فترات رأس المال)

**Location**: Navigation → "إدارة رأس المال" → "فترات رأس المال"

### List Page Columns:
- **period_number** - الفترة (Badge)
- **start_date** - من
- **end_date** - إلى (null = Open period)
- **net_profit** - صافي الربح
- **status** - الحالة (Open/Closed)
- **closedBy.name** - أُغلقت بواسطة
- **closed_at** - تاريخ الإغلاق

### View Page Sections:

**معلومات الفترة (Period Info):**
- Period number
- Start/End dates
- Status

**الملخص المالي (Financial Summary)** _(Visible when closed)_:
- Total Revenue
- Total Expenses
- Net Profit

**نسب الشركاء (Partner Percentages):**
Shows locked percentages for that period via relation manager:
- Partner name
- Equity percentage (locked)
- Capital at start
- Profit allocated
- Capital injected
- Drawings taken

### Actions:
- **"إغلاق الفترة"** (Close Period) - Only for open periods
  - Asks for end date and notes
  - Calculates and allocates profit automatically
  - Marks period as closed

---

## 3. FixedAssetResource (الأصول الثابتة)

### Form - New Section: "معلومات الاستهلاك"
**Collapsible section** with depreciation settings:

Input Fields:
- **useful_life_years** - العمر الإنتاجي (Default: 5 years)
- **salvage_value** - قيمة الخردة (Default: 0)
- **depreciation_method** - طريقة الاستهلاك (Fixed: "القسط الثابت")
- **is_contributed_asset** - مساهمة من شريك؟ (Auto-set if funding_method = equity)
- **contributing_partner_id** - الشريك المساهم (Select from shareholders)

Read-only Info (Placeholders):
- **monthly_depreciation_info** - الاستهلاك الشهري (Calculated)
- **accumulated_depreciation_info** - الاستهلاك المتراكم (Shows current total)
- **book_value_info** - القيمة الدفترية (Purchase - Accumulated)
- **last_depreciation_info** - آخر استهلاك (Last processed date)

### Table - New Columns
All depreciation columns are **toggleable** and **hidden by default**:

- **book_value** - القيمة الدفترية (Purchase - Accumulated, badge)
- **accumulated_depreciation** - الاستهلاك المتراكم
- **is_contributed_asset** - مساهمة شريك (Boolean icon)
- **contributingPartner.name** - الشريك المساهم

### How to View:
1. Go to `/admin/fixed-assets`
2. Click column selector (top right)
3. Enable depreciation columns as needed
4. Edit/Create asset to see full depreciation section

---

## 4. Key Differences: Two Types of Balances

### For Partners:

**current_balance** (الرصيد الجاري):
- Partner's **Current Account** balance
- Tracks: drawings, personal loans, advances
- This is what customers/suppliers owe to/from business
- Can be positive or negative

**current_capital** (رأس المال):
- Partner's **Capital Account** (equity/ownership)
- Tracks: capital investments, profit allocations
- Only for shareholders
- Determines equity percentages
- Always positive

**Example**:
- Partner A has:
  - `current_capital` = 150,000 ج.م (owns 60% of business)
  - `current_balance` = -5,000 ج.م (owes business 5,000 for drawings)

---

## Testing the Updates

### Test 1: View Shareholder Capital in Partners Table
```
1. Go to /admin/partners
2. Filter: النوع = "شريك (مساهم)"
3. Click column selector (⋮ icon)
4. Enable: "رأس المال", "نسبة الملكية"
5. See: محمد أحمد (60%, 150,000 ج.م), أحمد علي (40%, 100,000 ج.م)
```

### Test 2: View Equity Period
```
1. Go to /admin/equity-periods
2. Click on Period #1
3. See: Period info, partner percentages locked at 60/40
4. Note: Period is "مفتوحة" (Open)
```

### Test 3: Create Fixed Asset with Depreciation
```
1. Go to /admin/fixed-assets
2. Click "Create"
3. Fill basic info (name, amount, date)
4. Set funding method: "مساهمة رأسمالية من شريك"
5. Select partner
6. Expand "معلومات الاستهلاك"
7. Set useful life: 5 years
8. Set salvage value: 50,000
9. See monthly depreciation calculated automatically
10. Save
```

---

## Summary - What You Can Now See

✅ **In Partners Table** (with columns enabled):
- Current balance (account ledger)
- Capital amount (equity)
- Equity percentage
- Manager status

✅ **In Equity Periods**:
- All periods (open/closed)
- Locked partner percentages per period
- Profit allocations
- Financial summaries

✅ **In Fixed Assets**:
- Depreciation settings
- Book value (current worth)
- Accumulated depreciation
- Contributing partner (if applicable)

All fields are properly integrated with the capital management system and will update automatically when using the CapitalService methods (injectCapital, closePeriod, etc.).
