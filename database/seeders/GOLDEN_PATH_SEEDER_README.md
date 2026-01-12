# Golden Path Seeder Documentation

## Overview

The `GoldenPathSeeder` creates a **logically consistent, chronologically correct** business scenario for your ERP system. Unlike random seeders that generate independent records, this seeder tells a cohesive business story that follows strict accounting and inventory management rules.

## What Makes It "Golden Path"?

The term "Golden Path" refers to a **best-case scenario** where everything follows proper business logic:

- ✅ **No negative stock** - You can't sell what you haven't purchased
- ✅ **No financial discrepancies** - All money movements are tracked and balanced
- ✅ **Chronologically correct** - Events happen in a logical order
- ✅ **Realistic pricing** - Sale prices always exceed cost prices with proper margins
- ✅ **Balanced accounts** - Treasury balances match expectations

## Business Story Flow

### Day 1: Initial Capital Investment
```
Shareholder 1 (60%) → 300,000 EGP
Shareholder 2 (30%) → 150,000 EGP
Shareholder 3 (10%) →  50,000 EGP
-----------------------------------
Total Initial Capital: 500,000 EGP
Treasury Balance: 500,000 EGP
```

### Days 1-10: Building Inventory (Purchase Phase)
- **1-2 purchase invoices per day**
- Mix of cash (first 3 days) and credit purchases
- 3-5 products per invoice
- Realistic quantities (20-100 units per product)
- Small discounts (0-5%)
- **Treasury Impact**: Outgoing payments reduce balance

**Example:**
```
Day 1: Purchase from "شركة دمياط للأدوات" - 15,000 EGP (Cash)
  - 50 units of "طبق تقديم" @ 10 EGP
  - 30 units of "كوب شاي" @ 5 EGP
  - 80 units of "علبة حفظ" @ 7 EGP
Treasury Balance: 485,000 EGP
```

### Days 5-30: Sales Phase
- **2-4 sales invoices per day**
- Only sells products that are in stock
- Mix of cash (40%) and credit (60%) sales
- 1-4 products per invoice
- Realistic quantities (10-30% of available stock)
- Discounts (0-10%)
- **Treasury Impact**: Cash sales increase balance immediately

**Example:**
```
Day 7: Sale to "محمد إبراهيم أحمد" - 675 EGP (Cash)
  - 5 units of "طبق تقديم" @ 13.50 EGP
Treasury Balance: 485,675 EGP
```

### Days 10-30: Payment Collections
- Collect outstanding customer payments every 3 days
- Pay 50-100% of remaining balance
- Occasional settlement discounts (5%)
- **Treasury Impact**: Collections increase balance

### Days 12-30: Supplier Payments
- Pay suppliers every 4 days
- Pay 40-80% of outstanding amounts
- Occasional early payment discounts (3%)
- **Treasury Impact**: Payments decrease balance

### Every 5 Days: Operating Expenses
- Rent: 10,000 EGP
- Salaries: 25,000 EGP
- Utilities: 1,500 EGP
- Marketing: 3,000 EGP
- Maintenance: 2,000 EGP
- **Treasury Impact**: Expenses decrease balance

### Occasional Events
- **Returns** (Days 15, 22, 28): Sales and purchase returns
- **Revenues** (Days 8, 18, 25): Commission, services, bank interest

## Key Features

### 1. Inventory Tracking
```php
// In-memory inventory tracking prevents overselling
$this->inventoryLevels[$product->id] += $purchaseQty;  // Purchase
$this->inventoryLevels[$product->id] -= $saleQty;      // Sale

// Sales only happen if stock is available
if ($this->inventoryLevels[$product->id] > 10) {
    // Create sale
}
```

### 2. Financial Balancing
```php
// Expected treasury balance is tracked at every step
$this->expectedTreasuryBalance += $salesCollection;
$this->expectedTreasuryBalance -= $purchasePayment;
$this->expectedTreasuryBalance -= $expense;

// Verified at the end
$actualBalance = $this->treasuryService->getTreasuryBalance($treasury->id);
assert($actualBalance === $this->expectedTreasuryBalance);
```

### 3. Realistic Pricing
```php
// Products have cost-based pricing with proper margins
$cost = 10;           // Purchase cost
$margin = 0.35;       // 35% markup
$retailPrice = $cost * (1 + $margin);  // = 13.50
$wholesalePrice = $cost * (1 + $margin * 0.8);  // = 12.80
```

### 4. Payment Logic
```php
// Cash invoices: Full payment immediately
if ($paymentMethod === 'cash') {
    $paidAmount = $total;
    $remainingAmount = 0;
}

// Credit invoices: Partial or deferred payment
if ($paymentMethod === 'credit') {
    $paidAmount = rand(0, 1) ? $total * 0.5 : 0;  // 50% or nothing
    $remainingAmount = $total - $paidAmount;
}
```

## Data Created

| Entity | Count | Details |
|--------|-------|---------|
| **Shareholders** | 3 | Initial capital contributors |
| **Suppliers** | 5 | Purchase inventory from them |
| **Customers** | 10 | Sell products to them |
| **Products** | 20 | Kitchen/home goods with realistic pricing |
| **Treasuries** | 2 | Main treasury (cash) + Bank account |
| **Purchase Invoices** | ~15-20 | Builds inventory (Days 1-10) |
| **Sales Invoices** | ~80-100 | Sells from available stock (Days 5-30) |
| **Returns** | ~3-6 | Both sales and purchase returns |
| **Expenses** | ~6 | Operating expenses (every 5 days) |
| **Revenues** | ~3 | Additional income sources |
| **Payments** | ~15-20 | Customer collections + supplier payments |

## Strict Rules Applied

### ✅ Chronological Order
```
1. Initial Capital Deposit
2. Purchase Invoices (Build Stock)
3. Sales Invoices (Sell Stock)
4. Collect Payments
5. Pay Suppliers
6. Record Expenses
7. Process Returns
```

### ✅ Pricing Logic
```
Cost Price: 10 EGP
↓ (25-40% margin)
Sale Price: 13.50 EGP
✓ Sale > Cost ✓
```

### ✅ Inventory Consistency
```
Purchase: +100 units
Sale 1: -30 units → Stock: 70 units ✓
Sale 2: -40 units → Stock: 30 units ✓
Sale 3: -50 units → ❌ BLOCKED (insufficient stock)
```

### ✅ Financial Balancing
```
Initial Capital:        +500,000
Purchase Payments:      -150,000
Sales Collections:      +200,000
Expenses:               -60,000
Revenues:               +10,000
----------------------------------
Expected Balance:       500,000 EGP
Actual Balance:         500,000 EGP ✓
```

## Usage

### Option 1: Run Full Database Seeder (Recommended)
```bash
# This will run all seeders including GoldenPathSeeder
php artisan migrate:fresh --seed
```

### Option 2: Run Only GoldenPathSeeder
```bash
# Make sure foundation seeders run first
php artisan db:seed --class=GeneralSettingSeeder
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=AdminUserSeeder
php artisan db:seed --class=UnitSeeder
php artisan db:seed --class=WarehouseSeeder
php artisan db:seed --class=ProductCategorySeeder

# Then run GoldenPathSeeder
php artisan db:seed --class=GoldenPathSeeder
```

### Option 3: Use Old Random Seeder
If you prefer the old random data approach:
```php
// In DatabaseSeeder.php, comment out GoldenPathSeeder and uncomment:
// ComprehensiveDatabaseSeeder::class,
```

## Verification

The seeder performs automatic verification at the end:

### 1. Treasury Balance Verification
```
Expected Treasury Balance: 485,340.00 EGP
Actual Treasury Balance:   485,340.00 EGP
✓ Treasury balances match perfectly!
```

### 2. Stock Verification
```
📊 Stock Verification:
  ✓ No negative stock detected
```

### 3. Financial Log
The seeder maintains a detailed financial log of all transactions:
```php
[
    'date' => '2026-01-11',
    'type' => 'Purchase Payment',
    'amount' => -15000.00,
    'balance' => 485000.00,
    'reference' => 'PUR-00001'
]
```

## Summary Output

At the end of seeding, you'll see a comprehensive summary:
```
================================================================================
📊 GOLDEN PATH SEEDER SUMMARY
================================================================================
👥 Partners: 10 customers, 5 suppliers, 3 shareholders
📦 Products: 20 products
📄 Invoices: 18 purchases, 95 sales
↩️  Returns: 4 sales returns, 0 purchase returns
💰 Finance: 6 expenses (60,000.00 EGP), 3 revenues (10,500.00 EGP)
🏦 Main Treasury Balance: 485,340.00 EGP
📊 Total Stock Value: 87,250.00 EGP
================================================================================
```

## Advanced Customization

### Adjust Business Parameters
You can easily modify the seeder to change business parameters:

```php
// In depositInitialCapital()
$totalCapital = 1000000; // Change initial capital

// In simulateBusinessDays()
$this->simulateBusinessDays(60); // Simulate 60 days instead of 30

// In executePurchaseDay()
$quantity = rand(50, 200); // Buy more inventory

// In executeSalesDay()
$invoiceCount = rand(4, 8); // Create more sales per day
```

### Add New Transaction Types
```php
// In simulateBusinessDays()
if ($day % 7 === 0) {
    $this->transferToBank(); // Transfer cash to bank weekly
}
```

### Adjust Payment Behavior
```php
// In executeSalesDay()
$paymentMethod = rand(0, 100) < 70 ? 'cash' : 'credit'; // 70% cash, 30% credit
```

## Troubleshooting

### Issue: "No admin user found"
**Solution:** Run `AdminUserSeeder` first
```bash
php artisan db:seed --class=AdminUserSeeder
```

### Issue: "Units not found"
**Solution:** Run `UnitSeeder` first
```bash
php artisan db:seed --class=UnitSeeder
```

### Issue: "No product category found"
**Solution:** Run `ProductCategorySeeder` first
```bash
php artisan db:seed --class=ProductCategorySeeder
```

### Issue: "Insufficient funds"
This is **expected behavior**! The seeder checks treasury balance before creating expenses. If you see this warning, it means:
- The business is running out of cash (realistic scenario)
- More customer collections are needed
- Expenses are being skipped to maintain positive balance

### Issue: "Failed to post invoice"
This usually means:
- **For purchases:** No problem (maybe validation failed)
- **For sales:** Insufficient stock (correct behavior - prevents overselling)

The seeder will log these and continue. This is intentional to show realistic business constraints.

## Comparison: Old vs New Seeder

| Feature | ComprehensiveDatabaseSeeder | GoldenPathSeeder |
|---------|----------------------------|------------------|
| **Approach** | Random independent records | Cohesive business story |
| **Chronology** | Random dates | Strictly chronological |
| **Inventory** | Can go negative ❌ | Always positive ✓ |
| **Treasury** | May not balance ❌ | Always balanced ✓ |
| **Pricing** | Random prices | Cost + Margin ✓ |
| **Payment Logic** | Random | Follows rules ✓ |
| **Verification** | None | Automatic ✓ |
| **Data Volume** | High (100+ records) | Moderate (realistic) |
| **Use Case** | Stress testing UI | Demo, training, development |

## Best Practices

1. **Development**: Use `GoldenPathSeeder` for consistent, predictable data
2. **Demo/Training**: Use `GoldenPathSeeder` to show proper business flow
3. **Testing Edge Cases**: Use `ComprehensiveDatabaseSeeder` for random scenarios
4. **Production**: Never use seeders in production! Use data migration instead

## Contributing

To improve the GoldenPathSeeder:

1. Add more realistic product categories
2. Implement warehouse transfers
3. Add more complex payment scenarios (installments)
4. Include fixed asset depreciation
5. Add tax calculations

## Support

For issues or questions:
- Check the logs in the seeder output
- Review the financial log array
- Verify all foundation seeders ran first
- Check database constraints and relationships

---

**Created by:** Senior Laravel Developer with Accounting Domain Knowledge
**Last Updated:** 2026-01-11
**Version:** 1.0.0
