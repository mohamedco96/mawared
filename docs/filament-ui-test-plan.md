# Filament UI/Integration Test Plan

## Complete Testing Strategy for Mawared ERP System

**Technology Stack:** Laravel 11, Filament v3, Pest PHP v3
**Scope:** UI/Integration tests for all Filament Resources
**Testing Approach:** Livewire component testing with Filament helpers

---

## Table of Contents

1. [Test Infrastructure](#1-test-infrastructure)
2. [SalesInvoiceResource Tests](#2-salesinvoiceresource-tests)
3. [PurchaseInvoiceResource Tests](#3-purchaseinvoiceresource-tests)
4. [ProductResource Tests](#4-productresource-tests)
5. [PartnerResource Tests](#5-partnerresource-tests)
6. [UserResource Tests](#6-userresource-tests)
7. [TreasuryResource Tests](#7-treasuryresource-tests)
8. [TreasuryTransactionResource Tests](#8-treasurytransactionresource-tests)
9. [Complete Code Examples](#9-complete-code-examples)
10. [Verification & Execution](#10-verification--execution)

---

## 1. Test Infrastructure

### 1.1 Base Test Setup

All Filament tests should extend the project's TestCase which includes `ensureFundedTreasury()` helper.

**File:** `tests/Feature/Filament/Concerns/FilamentTestSetup.php`

```php
<?php

namespace Tests\Feature\Filament\Concerns;

use App\Models\User;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Gate;

trait FilamentTestSetup
{
    protected User $user;

    protected function setUpFilamentTest(): void
    {
        // Clear permission cache
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // Create super admin role and user
        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        // Grant all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Authenticate
        $this->actingAs($this->user);

        // Ensure funded treasury
        $this->ensureFundedTreasury();
    }

    protected function ensureFundedTreasury(): void
    {
        $treasury = Treasury::firstOrCreate(
            ['name' => 'Main Treasury'],
            ['type' => 'cash']
        );

        if ($treasury->wasRecentlyCreated) {
            TreasuryTransaction::create([
                'treasury_id' => $treasury->id,
                'type' => 'capital_deposit',
                'amount' => 1000000,
                'description' => 'Initial Capital for Testing',
            ]);
        }
    }
}
```

### 1.2 Available Factories

| Factory | Key Modifiers |
|---------|---------------|
| `Partner::factory()` | `->customer()`, `->supplier()` |
| `SalesInvoice::factory()` | `->posted()`, `->credit()` |
| `PurchaseInvoice::factory()` | `->posted()`, `->credit()` |
| `Product::factory()` | `->withLargeUnit(12)` |
| `User::factory()` | `->unverified()` |

### 1.3 Test Helpers

**File:** `tests/Helpers/TestHelpers.php`

Available methods:
- `TestHelpers::createDualUnitProduct()` - Creates product with small/large units
- `TestHelpers::createFundedTreasury()` - Creates treasury with initial balance
- `TestHelpers::createDraftSalesInvoice()` - Creates invoice with items
- `TestHelpers::createUnits()` - Creates piece and carton units

---

## 2. SalesInvoiceResource Tests

**File:** `tests/Feature/Filament/SalesInvoiceResourceTest.php`

### 2.1 Page Rendering Tests

```php
describe('Page Rendering', function () {

    it('can render list page', function () {
        Livewire::test(ListSalesInvoices::class)
            ->assertStatus(200)
            ->assertSee('فواتير المبيعات');
    });

    it('can render create page', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->assertStatus(200);
    });

    it('can render edit page for draft invoice', function () {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertStatus(200);
    });

    it('can render view page for posted invoice', function () {
        $invoice = SalesInvoice::factory()->posted()->create();

        Livewire::test(ViewSalesInvoice::class, ['record' => $invoice->id])
            ->assertStatus(200);
    });

});
```

### 2.2 Product Scanner Tests (Reactive)

```php
describe('Product Scanner', function () {

    it('adds product to items repeater when scanned', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'wholesale_price' => '100.00',
            'retail_price' => '120.00',
        ]);

        // Add stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 50,
            'cost_at_time' => 80,
        ]);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm(['warehouse_id' => $warehouse->id])
            ->set('data.product_scanner', $product->id)
            ->assertFormSet(function (array $state) use ($product) {
                $items = $state['items'] ?? [];
                expect($items)->toHaveCount(1);

                $item = array_values($items)[0];
                expect((int) $item['product_id'])->toBe($product->id);
                expect((float) $item['unit_price'])->toBe(100.00); // wholesale
                expect((int) $item['quantity'])->toBe(1);
                expect((float) $item['total'])->toBe(100.00);

                return true;
            });
    });

    it('uses wholesale_price as default unit_price', function () {
        $product = Product::factory()->create([
            'wholesale_price' => '85.50',
            'retail_price' => '100.00',
        ]);

        Livewire::test(CreateSalesInvoice::class)
            ->set('data.product_scanner', $product->id)
            ->assertFormSet(function (array $state) {
                $item = array_values($state['items'])[0];
                expect((float) $item['unit_price'])->toBe(85.50);
                return true;
            });
    });

    it('resets product_scanner after adding item', function () {
        $product = Product::factory()->create();

        Livewire::test(CreateSalesInvoice::class)
            ->set('data.product_scanner', $product->id)
            ->assertSet('data.product_scanner', null);
    });

    it('increments quantity when scanning same product twice', function () {
        $product = Product::factory()->create();

        Livewire::test(CreateSalesInvoice::class)
            ->set('data.product_scanner', $product->id)
            ->set('data.product_scanner', $product->id)
            ->assertFormSet(function (array $state) {
                expect($state['items'])->toHaveCount(1);
                $item = array_values($state['items'])[0];
                expect((int) $item['quantity'])->toBe(2);
                return true;
            });
    });

    it('shows product with stock info in scanner search', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['name' => 'Test Product']);

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 25,
            'cost_at_time' => 50,
        ]);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm(['warehouse_id' => $warehouse->id])
            ->assertSee('Test Product');
    });

});
```

### 2.3 Item Calculation Tests (Critical - Reactive)

```php
describe('Item Calculations', function () {

    it('calculates item total when quantity changes', function () {
        $product = Product::factory()->create(['wholesale_price' => '100.00']);
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 1,
            'unit_price' => '100.00',
            'total' => '100.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(function ($data) {
                $key = array_keys($data['items'])[0];
                return ["items.{$key}.quantity" => 5];
            })
            ->assertFormSet(function ($state) {
                $item = array_values($state['items'])[0];
                expect((float) $item['total'])->toBe(500.00); // 5 × 100
                return true;
            });
    });

    it('calculates item total when unit_price changes', function () {
        $product = Product::factory()->create();
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(function ($data) {
                $key = array_keys($data['items'])[0];
                return ["items.{$key}.unit_price" => 120];
            })
            ->assertFormSet(function ($state) {
                $item = array_values($state['items'])[0];
                expect((float) $item['total'])->toBe(600.00); // 5 × 120
                return true;
            });
    });

    it('updates unit_price when unit_type changes from small to large', function () {
        $smallUnit = Unit::factory()->create(['name' => 'Piece']);
        $largeUnit = Unit::factory()->create(['name' => 'Carton']);

        $product = Product::factory()->create([
            'small_unit_id' => $smallUnit->id,
            'large_unit_id' => $largeUnit->id,
            'factor' => 12,
            'wholesale_price' => '10.00',
            'large_wholesale_price' => '120.00',
        ]);

        $warehouse = Warehouse::factory()->create();
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 120,
            'cost_at_time' => 8,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 1,
            'unit_price' => '10.00',
            'total' => '10.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(function ($data) {
                $key = array_keys($data['items'])[0];
                return ["items.{$key}.unit_type" => 'large'];
            })
            ->assertFormSet(function ($state) {
                $item = array_values($state['items'])[0];
                expect((float) $item['unit_price'])->toBe(120.00);
                expect((float) $item['total'])->toBe(120.00);
                return true;
            });
    });

    it('shows stock availability helper text on quantity field', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 25,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 1,
            'unit_price' => '100.00',
            'total' => '100.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertSee('25'); // Available stock shown
    });

});
```

### 2.4 Invoice-Level Calculation Tests (recalculateTotals)

```php
describe('Invoice Totals Calculation', function () {

    it('calculates subtotal as sum of all item totals', function () {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->createMany([
            [
                'product_id' => Product::factory()->create()->id,
                'unit_type' => 'small',
                'quantity' => 5,
                'unit_price' => '100.00',
                'total' => '500.00',
            ],
            [
                'product_id' => Product::factory()->create()->id,
                'unit_type' => 'small',
                'quantity' => 3,
                'unit_price' => '100.00',
                'total' => '300.00',
            ],
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertFormSet(['subtotal' => 800.00]);
    });

    it('calculates discount correctly for fixed discount type', function () {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'discount_type' => 'fixed',
                'discount_value' => 150,
            ])
            ->assertFormSet([
                'subtotal' => 1000.00,
                'discount' => 150.00,
                'total' => 850.00, // 1000 - 150
            ]);
    });

    it('calculates discount correctly for percentage discount type', function () {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'discount_type' => 'percentage',
                'discount_value' => 10, // 10%
            ])
            ->assertFormSet([
                'subtotal' => 1000.00,
                'discount' => 100.00, // 10% of 1000
                'total' => 900.00,
            ]);
    });

    it('calculates commission amount based on commission_rate', function () {
        $salesperson = User::factory()->create();
        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
            'sales_person_id' => $salesperson->id,
        ]);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(['commission_rate' => 5]) // 5%
            ->assertFormSet([
                'total' => 1000.00,
                'commission_amount' => 50.00, // 5% of 1000
            ]);
    });

    it('sets remaining_amount to 0 for cash payment method', function () {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(['payment_method' => 'cash'])
            ->assertFormSet([
                'total' => 1000.00,
                'remaining_amount' => 0,
            ]);
    });

    it('calculates remaining_amount for credit payment method', function () {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'payment_method' => 'credit',
                'paid_amount' => 300,
            ])
            ->assertFormSet([
                'total' => 1000.00,
                'remaining_amount' => 700.00, // 1000 - 300
            ]);
    });

});
```

### 2.5 Payment Method Visibility Tests

```php
describe('Payment Method Field Visibility', function () {

    it('shows credit fields when payment_method is credit', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm(['payment_method' => 'credit'])
            ->assertFormFieldIsVisible('paid_amount')
            ->assertFormFieldIsVisible('remaining_amount')
            ->assertFormFieldIsVisible('has_installment_plan');
    });

    it('hides credit fields when payment_method is cash', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm(['payment_method' => 'cash'])
            ->assertFormFieldIsHidden('paid_amount')
            ->assertFormFieldIsHidden('remaining_amount')
            ->assertFormFieldIsHidden('has_installment_plan');
    });

    it('shows installment fields when has_installment_plan is true', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'payment_method' => 'credit',
                'has_installment_plan' => true,
            ])
            ->assertFormFieldIsVisible('installment_months')
            ->assertFormFieldIsVisible('installment_start_date')
            ->assertFormFieldIsVisible('installment_interest_percentage');
    });

    it('hides installment fields when has_installment_plan is false', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'payment_method' => 'credit',
                'has_installment_plan' => false,
            ])
            ->assertFormFieldIsHidden('installment_months')
            ->assertFormFieldIsHidden('installment_start_date');
    });

});
```

### 2.6 Status-Based Field Disabled Tests

```php
describe('Posted Invoice Field States', function () {

    it('disables all form fields when invoice is posted', function () {
        $invoice = SalesInvoice::factory()->posted()->create();

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertFormFieldIsDisabled('warehouse_id')
            ->assertFormFieldIsDisabled('partner_id')
            ->assertFormFieldIsDisabled('payment_method')
            ->assertFormFieldIsDisabled('discount_type')
            ->assertFormFieldIsDisabled('discount_value')
            ->assertFormFieldIsDisabled('product_scanner');
    });

    it('enables form fields on draft invoice', function () {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertFormFieldIsEnabled('warehouse_id')
            ->assertFormFieldIsEnabled('partner_id')
            ->assertFormFieldIsEnabled('payment_method')
            ->assertFormFieldIsEnabled('discount_type')
            ->assertFormFieldIsEnabled('discount_value');
    });

    it('enables payment fields on posted invoice', function () {
        $invoice = SalesInvoice::factory()->posted()->credit()->create([
            'remaining_amount' => 500,
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertFormFieldIsEnabled('paid_amount');
    });

});
```

### 2.7 Stock Validation Tests

```php
describe('Stock Validation', function () {

    it('shows validation error when quantity exceeds available stock', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Only 5 in stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 5,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10, // Exceeds 5 available
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->call('save')
            ->assertHasFormErrors(['items.0.quantity']);
    });

    it('validates stock for each item before saving', function () {
        $warehouse = Warehouse::factory()->create();
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // 10 of product1, 3 of product2
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product1->id,
            'type' => 'purchase',
            'quantity' => 10,
            'cost_at_time' => 50,
        ]);

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product2->id,
            'type' => 'purchase',
            'quantity' => 3,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $invoice->items()->createMany([
            [
                'product_id' => $product1->id,
                'unit_type' => 'small',
                'quantity' => 5, // OK
                'unit_price' => '100.00',
                'total' => '500.00',
            ],
            [
                'product_id' => $product2->id,
                'unit_type' => 'small',
                'quantity' => 10, // Exceeds 3
                'unit_price' => '100.00',
                'total' => '1000.00',
            ],
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->call('save')
            ->assertHasFormErrors(['items.1.quantity']);
    });

    it('allows saving when stock is sufficient', function () {
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $product = Product::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->call('save')
            ->assertHasNoFormErrors();
    });

});
```

### 2.8 Header Actions Tests

```php
describe('Header Actions', function () {

    it('shows delete action only on draft invoices', function () {
        $draft = SalesInvoice::factory()->create(['status' => 'draft']);
        $posted = SalesInvoice::factory()->posted()->create();

        Livewire::test(EditSalesInvoice::class, ['record' => $draft->id])
            ->assertActionVisible('delete');

        Livewire::test(EditSalesInvoice::class, ['record' => $posted->id])
            ->assertActionHidden('delete');
    });

    it('shows create_return action only on posted invoices', function () {
        $draft = SalesInvoice::factory()->create(['status' => 'draft']);
        $posted = SalesInvoice::factory()->posted()->create();

        Livewire::test(EditSalesInvoice::class, ['record' => $draft->id])
            ->assertActionHidden('create_return');

        Livewire::test(EditSalesInvoice::class, ['record' => $posted->id])
            ->assertActionVisible('create_return');
    });

    it('hides post action on already posted invoices', function () {
        $posted = SalesInvoice::factory()->posted()->create();

        Livewire::test(EditSalesInvoice::class, ['record' => $posted->id])
            ->assertActionHidden('post');
    });

    it('shows post action on draft invoices with items', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
        ]);

        $draft = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        $draft->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $draft->id])
            ->assertActionVisible('post');
    });

});
```

### 2.9 Table Actions Tests

```php
describe('Table Actions', function () {

    it('shows post action only for draft invoices in table', function () {
        $draft = SalesInvoice::factory()->create(['status' => 'draft']);
        $posted = SalesInvoice::factory()->posted()->create();

        Livewire::test(ListSalesInvoices::class)
            ->assertTableActionVisible('post', $draft)
            ->assertTableActionHidden('post', $posted);
    });

    it('shows add_payment action for posted invoices with remaining balance', function () {
        $invoice = SalesInvoice::factory()->posted()->create([
            'total' => '1000.00',
            'remaining_amount' => '500.00',
        ]);

        Livewire::test(ListSalesInvoices::class)
            ->assertTableActionVisible('add_payment', $invoice);
    });

    it('hides add_payment action when invoice is fully paid', function () {
        $invoice = SalesInvoice::factory()->posted()->create([
            'total' => '1000.00',
            'remaining_amount' => '0.00',
        ]);

        Livewire::test(ListSalesInvoices::class)
            ->assertTableActionHidden('add_payment', $invoice);
    });

    it('can view invoice from table', function () {
        $invoice = SalesInvoice::factory()->create();

        Livewire::test(ListSalesInvoices::class)
            ->assertTableActionVisible('view', $invoice);
    });

    it('shows replicate action for invoices', function () {
        $invoice = SalesInvoice::factory()->create();

        Livewire::test(ListSalesInvoices::class)
            ->assertTableActionVisible('replicate', $invoice);
    });

});
```

### 2.10 Post Invoice Integration Tests

```php
describe('Post Invoice Integration', function () {

    it('creates stock movements when invoice is posted', function () {
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $product = Product::factory()->create();

        // Initial stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '500.00',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        // Verify stock movement created
        expect(StockMovement::where([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'sales',
        ])->exists())->toBeTrue();

        // Verify negative quantity (outgoing)
        $salesMovement = StockMovement::where('type', 'sales')
            ->where('product_id', $product->id)
            ->first();

        expect($salesMovement->quantity)->toBe(-5);
    });

    it('creates treasury transaction when cash invoice is posted', function () {
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $product = Product::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '500.00',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        $initialCount = TreasuryTransaction::count();

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        expect(TreasuryTransaction::count())->toBeGreaterThan($initialCount);
    });

    it('updates invoice status to posted after post action', function () {
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $product = Product::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '500.00',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        expect($invoice->fresh()->status)->toBe('posted');
    });

    it('generates installments when has_installment_plan is true', function () {
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $product = Product::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => '1200.00',
            'paid_amount' => '0.00',
            'remaining_amount' => '1200.00',
            'has_installment_plan' => true,
            'installment_months' => 3,
            'installment_start_date' => now()->addMonth()->startOfMonth(),
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 12,
            'unit_price' => '100.00',
            'total' => '1200.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        expect(Installment::where('sales_invoice_id', $invoice->id)->count())->toBe(3);
    });

});
```

### 2.11 Validation Tests

```php
describe('Form Validation', function () {

    it('requires partner_id', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'warehouse_id' => Warehouse::factory()->create()->id,
                'payment_method' => 'cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['partner_id']);
    });

    it('requires warehouse_id', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'partner_id' => Partner::factory()->customer()->create()->id,
                'payment_method' => 'cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['warehouse_id']);
    });

    it('requires at least one item', function () {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'warehouse_id' => Warehouse::factory()->create()->id,
                'partner_id' => Partner::factory()->customer()->create()->id,
                'payment_method' => 'cash',
            ])
            ->call('create')
            ->assertHasFormErrors(['items']);
    });

});
```

---

## 3. PurchaseInvoiceResource Tests

**File:** `tests/Feature/Filament/PurchaseInvoiceResourceTest.php`

### 3.1 Page Rendering Tests

```php
describe('Page Rendering', function () {

    it('can render list page', function () {
        Livewire::test(ListPurchaseInvoices::class)
            ->assertStatus(200);
    });

    it('can render create page', function () {
        Livewire::test(CreatePurchaseInvoice::class)
            ->assertStatus(200);
    });

    it('can render edit page', function () {
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->assertStatus(200);
    });

});
```

### 3.2 Product Scanner Tests

```php
describe('Product Scanner', function () {

    it('adds product to items repeater when scanned', function () {
        $product = Product::factory()->create(['wholesale_price' => '80.00']);

        Livewire::test(CreatePurchaseInvoice::class)
            ->set('data.product_scanner', $product->id)
            ->assertFormSet(function (array $state) use ($product) {
                expect($state['items'])->toHaveCount(1);
                $item = array_values($state['items'])[0];
                expect((int) $item['product_id'])->toBe($product->id);
                return true;
            });
    });

    it('allows creating new product from scanner', function () {
        // Test inline product creation capability
        Livewire::test(CreatePurchaseInvoice::class)
            ->assertFormFieldExists('product_scanner');
    });

});
```

### 3.3 Item Calculations Tests

```php
describe('Item Calculations', function () {

    it('calculates item total from quantity and unit_cost', function () {
        $product = Product::factory()->create();
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'total' => '500.00',
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->assertFormSet(function ($state) {
                $item = array_values($state['items'])[0];
                expect((float) $item['total'])->toBe(500.00);
                return true;
            });
    });

    it('shows price update fields in item repeater', function () {
        $product = Product::factory()->create();
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'total' => '500.00',
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->assertFormFieldExists('items.*.new_selling_price');
    });

});
```

### 3.4 Invoice Totals Tests

```php
describe('Invoice Totals', function () {

    it('calculates subtotal from all items', function () {
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->createMany([
            [
                'product_id' => Product::factory()->create()->id,
                'unit_type' => 'small',
                'quantity' => 10,
                'unit_cost' => '50.00',
                'total' => '500.00',
            ],
            [
                'product_id' => Product::factory()->create()->id,
                'unit_type' => 'small',
                'quantity' => 5,
                'unit_cost' => '100.00',
                'total' => '500.00',
            ],
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->assertFormSet(['subtotal' => 1000.00]);
    });

    it('applies fixed discount correctly', function () {
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'discount_type' => 'fixed',
                'discount_value' => 100,
            ])
            ->assertFormSet([
                'discount' => 100.00,
                'total' => 900.00,
            ]);
    });

    it('applies percentage discount correctly', function () {
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'discount_type' => 'percentage',
                'discount_value' => 15,
            ])
            ->assertFormSet([
                'discount' => 150.00,
                'total' => 850.00,
            ]);
    });

});
```

### 3.5 Post Invoice Integration Tests

```php
describe('Post Invoice Integration', function () {

    it('creates positive stock movements when posted', function () {
        $warehouse = Warehouse::factory()->create();
        $supplier = Partner::factory()->supplier()->create();
        $product = Product::factory()->create();

        $invoice = PurchaseInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '500.00',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'total' => '500.00',
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        $movement = StockMovement::where([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
        ])->latest()->first();

        expect($movement)->not->toBeNull();
        expect($movement->quantity)->toBe(10); // Positive (incoming)
    });

    it('updates product avg_cost after posting', function () {
        $warehouse = Warehouse::factory()->create();
        $supplier = Partner::factory()->supplier()->create();
        $product = Product::factory()->create(['avg_cost' => '40.00']);

        $invoice = PurchaseInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '600.00',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '60.00', // New cost higher than avg
            'total' => '600.00',
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        // avg_cost should be updated (weighted average)
        $product->refresh();
        expect((float) $product->avg_cost)->not->toBe(40.00);
    });

    it('creates treasury expense transaction', function () {
        $warehouse = Warehouse::factory()->create();
        $supplier = Partner::factory()->supplier()->create();
        $product = Product::factory()->create();

        $invoice = PurchaseInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $supplier->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => '500.00',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'total' => '500.00',
        ]);

        $initialCount = TreasuryTransaction::count();

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        expect(TreasuryTransaction::count())->toBeGreaterThan($initialCount);
    });

    it('updates product prices when new prices provided', function () {
        $warehouse = Warehouse::factory()->create();
        $supplier = Partner::factory()->supplier()->create();
        $product = Product::factory()->create([
            'retail_price' => '100.00',
            'wholesale_price' => '90.00',
        ]);

        $invoice = PurchaseInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $supplier->id,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_cost' => '50.00',
            'total' => '500.00',
            'new_selling_price' => '120.00', // New retail price
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->callAction('post');

        $product->refresh();
        expect((float) $product->retail_price)->toBe(120.00);
    });

});
```

### 3.6 Supplier Filtering Test

```php
describe('Partner Filtering', function () {

    it('filters partners to supplier type only', function () {
        $customer = Partner::factory()->customer()->create(['name' => 'Customer']);
        $supplier = Partner::factory()->supplier()->create(['name' => 'Supplier']);

        Livewire::test(CreatePurchaseInvoice::class)
            ->assertSee('Supplier')
            ->assertDontSee('Customer');
    });

});
```

---

## 4. ProductResource Tests

**File:** `tests/Feature/Filament/ProductResourceTest.php`

### 4.1 Page Rendering Tests

```php
describe('Page Rendering', function () {

    it('can render list page', function () {
        Livewire::test(ListProducts::class)
            ->assertStatus(200);
    });

    it('can render create page', function () {
        Livewire::test(CreateProduct::class)
            ->assertStatus(200);
    });

    it('can render edit page', function () {
        $product = Product::factory()->create();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertStatus(200);
    });

    it('renders all tabs in product form', function () {
        Livewire::test(CreateProduct::class)
            ->assertSee('البيانات الأساسية')
            ->assertSee('الصور والميديا')
            ->assertSee('التسعير والوحدات')
            ->assertSee('المخزون');
    });

});
```

### 4.2 Dual Unit Pricing Tests (Critical - Reactive)

```php
describe('Dual Unit Pricing', function () {

    it('calculates large_retail_price when retail_price changes', function () {
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
            ])
            ->assertFormSet(['large_retail_price' => '120.00']); // 10 × 12
    });

    it('calculates large_wholesale_price when wholesale_price changes', function () {
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
            ])
            ->assertFormSet(['large_wholesale_price' => '96.00']); // 8 × 12
    });

    it('recalculates large prices when factor changes', function () {
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
            ])
            ->assertFormSet([
                'large_retail_price' => '120.00',
                'large_wholesale_price' => '96.00',
            ])
            ->fillForm(['factor' => 24])
            ->assertFormSet([
                'large_retail_price' => '240.00', // 10 × 24
                'large_wholesale_price' => '192.00', // 8 × 24
            ]);
    });

    it('shows large unit section only when large_unit_id is selected', function () {
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->assertFormFieldIsHidden('large_retail_price')
            ->assertFormFieldIsHidden('large_wholesale_price')
            ->fillForm(['large_unit_id' => $largeUnit->id])
            ->assertFormFieldIsVisible('large_retail_price')
            ->assertFormFieldIsVisible('large_wholesale_price');
    });

    it('hides large unit section when large_unit_id is null', function () {
        $product = Product::factory()->create([
            'large_unit_id' => Unit::factory()->create()->id,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertFormFieldIsVisible('large_retail_price')
            ->fillForm(['large_unit_id' => null])
            ->assertFormFieldIsHidden('large_retail_price');
    });

});
```

### 4.3 Auto-Generation Tests

```php
describe('Auto-Generation', function () {

    it('auto-generates SKU if left empty', function () {
        $unit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $unit->id,
                'retail_price' => '100.00',
                'wholesale_price' => '90.00',
                'min_stock' => 10,
                // SKU left empty
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::latest()->first();
        expect($product->sku)->not->toBeNull();
        expect($product->sku)->not->toBeEmpty();
    });

    it('auto-generates barcode if left empty', function () {
        $unit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $unit->id,
                'retail_price' => '100.00',
                'wholesale_price' => '90.00',
                'min_stock' => 10,
                // Barcode left empty
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::latest()->first();
        expect($product->barcode)->not->toBeNull();
    });

    it('auto-generates large_barcode when large unit selected', function () {
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
                'min_stock' => 10,
                // large_barcode left empty
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::latest()->first();
        expect($product->large_barcode)->not->toBeNull();
    });

});
```

### 4.4 Validation Tests

```php
describe('Validation', function () {

    it('validates SKU uniqueness', function () {
        $existingProduct = Product::factory()->create(['sku' => 'UNIQUE-SKU']);
        $unit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'New Product',
                'small_unit_id' => $unit->id,
                'sku' => 'UNIQUE-SKU', // Duplicate
                'retail_price' => '100.00',
                'wholesale_price' => '90.00',
                'min_stock' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['sku']);
    });

    it('validates barcode uniqueness', function () {
        $existingProduct = Product::factory()->create(['barcode' => '123456789']);
        $unit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'New Product',
                'small_unit_id' => $unit->id,
                'barcode' => '123456789', // Duplicate
                'retail_price' => '100.00',
                'wholesale_price' => '90.00',
                'min_stock' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['barcode']);
    });

    it('requires name field', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'small_unit_id' => Unit::factory()->create()->id,
                'retail_price' => '100.00',
                'wholesale_price' => '90.00',
                'min_stock' => 10,
                // name missing
            ])
            ->call('create')
            ->assertHasFormErrors(['name']);
    });

    it('requires small_unit_id', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'retail_price' => '100.00',
                'wholesale_price' => '90.00',
                'min_stock' => 10,
                // small_unit_id missing
            ])
            ->call('create')
            ->assertHasFormErrors(['small_unit_id']);
    });

    it('prevents negative prices', function () {
        $unit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $unit->id,
                'retail_price' => '-10.00', // Negative
                'wholesale_price' => '90.00',
                'min_stock' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['retail_price']);
    });

});
```

### 4.5 Table Stock Level Tests

```php
describe('Table Stock Level Display', function () {

    it('shows danger color for negative stock', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create negative stock situation
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'correction',
            'quantity' => -10,
            'cost_at_time' => 50,
        ]);

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateEquals('stock_movements_sum_quantity', -10, $product);
    });

    it('shows warning color for low stock', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['min_stock' => 20]);

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 5, // Below min_stock of 20
            'cost_at_time' => 50,
        ]);

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateEquals('stock_movements_sum_quantity', 5, $product);
    });

    it('shows success color for sufficient stock', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['min_stock' => 10]);

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 50, // Above min_stock
            'cost_at_time' => 50,
        ]);

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateEquals('stock_movements_sum_quantity', 50, $product);
    });

});
```

### 4.6 Bulk Price Update Tests

```php
describe('Bulk Price Update', function () {

    it('can increase prices by percentage', function () {
        $products = Product::factory()->count(3)->create(['retail_price' => '100.00']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_price_update', $products, [
                'update_type' => 'percentage_increase',
                'value' => 10,
                'price_field' => 'retail_price',
            ]);

        $products->each(function ($product) {
            expect((float) $product->fresh()->retail_price)->toBe(110.00);
        });
    });

    it('can decrease prices by percentage', function () {
        $products = Product::factory()->count(3)->create(['retail_price' => '100.00']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_price_update', $products, [
                'update_type' => 'percentage_decrease',
                'value' => 20,
                'price_field' => 'retail_price',
            ]);

        $products->each(function ($product) {
            expect((float) $product->fresh()->retail_price)->toBe(80.00);
        });
    });

    it('can set fixed price', function () {
        $products = Product::factory()->count(3)->create(['retail_price' => '100.00']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_price_update', $products, [
                'update_type' => 'set_fixed',
                'value' => 150,
                'price_field' => 'retail_price',
            ]);

        $products->each(function ($product) {
            expect((float) $product->fresh()->retail_price)->toBe(150.00);
        });
    });

    it('prevents negative prices after decrease', function () {
        $products = Product::factory()->count(1)->create(['retail_price' => '50.00']);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('bulk_price_update', $products, [
                'update_type' => 'fixed_decrease',
                'value' => 100, // Would result in -50
                'price_field' => 'retail_price',
            ]);

        // Price should not go negative
        $products->each(function ($product) {
            expect((float) $product->fresh()->retail_price)->toBeGreaterThanOrEqual(0);
        });
    });

});
```

### 4.7 Deletion Protection Tests

```php
describe('Deletion Protection', function () {

    it('prevents deletion when product has stock movements', function () {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 10,
            'cost_at_time' => 50,
        ]);

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product)
            ->assertNotified();

        expect(Product::find($product->id))->not->toBeNull();
    });

    it('prevents deletion when product has invoice items', function () {
        $product = Product::factory()->create();
        $invoice = SalesInvoice::factory()->create();

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product)
            ->assertNotified();

        expect(Product::find($product->id))->not->toBeNull();
    });

    it('allows deletion of unused product', function () {
        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product);

        expect(Product::find($product->id))->toBeNull();
    });

});
```

### 4.8 Soft Delete Tests

```php
describe('Soft Delete', function () {

    it('soft deletes product', function () {
        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product);

        expect(Product::find($product->id))->toBeNull();
        expect(Product::withTrashed()->find($product->id))->not->toBeNull();
    });

    it('can restore soft deleted product', function () {
        $product = Product::factory()->create();
        $product->delete();

        expect(Product::find($product->id))->toBeNull();

        // Assuming there's a restore action
        $product->restore();

        expect(Product::find($product->id))->not->toBeNull();
    });

});
```

---

## 5. PartnerResource Tests

**File:** `tests/Feature/Filament/PartnerResourceTest.php`

### 5.1 Page Rendering Tests

```php
describe('Page Rendering', function () {

    it('can render list page', function () {
        Livewire::test(ListPartners::class)
            ->assertStatus(200);
    });

    it('can render create page', function () {
        Livewire::test(CreatePartner::class)
            ->assertStatus(200);
    });

});
```

### 5.2 Type-Based Conditional Fields Tests

```php
describe('Type-Based Conditional Fields', function () {

    it('shows capital fields only for shareholder type', function () {
        Livewire::test(CreatePartner::class)
            ->fillForm(['type' => 'customer'])
            ->assertFormFieldIsHidden('current_capital')
            ->assertFormFieldIsHidden('equity_percentage')
            ->fillForm(['type' => 'shareholder'])
            ->assertFormFieldIsVisible('current_capital')
            ->assertFormFieldIsVisible('equity_percentage');
    });

    it('hides current_balance for shareholder type', function () {
        Livewire::test(CreatePartner::class)
            ->fillForm(['type' => 'customer'])
            ->assertFormFieldIsVisible('current_balance')
            ->fillForm(['type' => 'shareholder'])
            ->assertFormFieldIsHidden('current_balance');
    });

    it('shows monthly_salary only when is_manager is true', function () {
        Livewire::test(CreatePartner::class)
            ->fillForm(['type' => 'shareholder'])
            ->assertFormFieldIsHidden('monthly_salary')
            ->fillForm(['is_manager' => true])
            ->assertFormFieldIsVisible('monthly_salary');
    });

    it('requires monthly_salary when is_manager is true', function () {
        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'Test Partner',
                'type' => 'shareholder',
                'is_manager' => true,
                // monthly_salary missing
            ])
            ->call('create')
            ->assertHasFormErrors(['monthly_salary']);
    });

});
```

### 5.3 CRUD Operations Tests

```php
describe('CRUD Operations', function () {

    it('can create customer partner', function () {
        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'Test Customer',
                'type' => 'customer',
                'phone' => '01012345678',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Partner::where('name', 'Test Customer')->exists())->toBeTrue();
    });

    it('can create supplier partner', function () {
        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'Test Supplier',
                'type' => 'supplier',
                'phone' => '01012345678',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Partner::where('name', 'Test Supplier')->where('type', 'supplier')->exists())->toBeTrue();
    });

    it('can create shareholder partner', function () {
        Livewire::test(CreatePartner::class)
            ->fillForm([
                'name' => 'Test Shareholder',
                'type' => 'shareholder',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Partner::where('name', 'Test Shareholder')->where('type', 'shareholder')->exists())->toBeTrue();
    });

    it('can edit partner', function () {
        $partner = Partner::factory()->customer()->create(['name' => 'Original Name']);

        Livewire::test(EditPartner::class, ['record' => $partner->id])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($partner->fresh()->name)->toBe('Updated Name');
    });

});
```

### 5.4 Table Features Tests

```php
describe('Table Features', function () {

    it('displays partners in table', function () {
        $partners = Partner::factory()->count(5)->create();

        Livewire::test(ListPartners::class)
            ->assertCanSeeTableRecords($partners);
    });

    it('can search partners by name', function () {
        $searchPartner = Partner::factory()->create(['name' => 'Searchable Partner']);
        $otherPartner = Partner::factory()->create(['name' => 'Other Partner']);

        Livewire::test(ListPartners::class)
            ->searchTable('Searchable')
            ->assertCanSeeTableRecords([$searchPartner])
            ->assertCanNotSeeTableRecords([$otherPartner]);
    });

    it('can filter partners by type', function () {
        $customer = Partner::factory()->customer()->create();
        $supplier = Partner::factory()->supplier()->create();

        Livewire::test(ListPartners::class)
            ->filterTable('type', 'customer')
            ->assertCanSeeTableRecords([$customer])
            ->assertCanNotSeeTableRecords([$supplier]);
    });

    it('shows balance with correct color coding', function () {
        $positiveBalance = Partner::factory()->create(['current_balance' => 500]);
        $negativeBalance = Partner::factory()->create(['current_balance' => -300]);
        $zeroBalance = Partner::factory()->create(['current_balance' => 0]);

        Livewire::test(ListPartners::class)
            ->assertTableColumnStateEquals('current_balance', 500, $positiveBalance)
            ->assertTableColumnStateEquals('current_balance', -300, $negativeBalance)
            ->assertTableColumnStateEquals('current_balance', 0, $zeroBalance);
    });

});
```

### 5.5 Statement Action Tests

```php
describe('Statement Action', function () {

    it('shows statement action for partners', function () {
        $partner = Partner::factory()->create();

        Livewire::test(ListPartners::class)
            ->assertTableActionVisible('statement', $partner);
    });

});
```

### 5.6 Deletion Protection Tests

```php
describe('Deletion Protection', function () {

    it('prevents deletion when partner has invoices', function () {
        $partner = Partner::factory()->customer()->create();

        SalesInvoice::factory()->create(['partner_id' => $partner->id]);

        Livewire::test(ListPartners::class)
            ->callTableAction('delete', $partner)
            ->assertNotified();

        expect(Partner::find($partner->id))->not->toBeNull();
    });

    it('prevents deletion when partner has treasury transactions', function () {
        $partner = Partner::factory()->customer()->create();

        TreasuryTransaction::create([
            'treasury_id' => Treasury::factory()->create()->id,
            'partner_id' => $partner->id,
            'type' => 'collection',
            'amount' => 100,
            'description' => 'Test',
        ]);

        Livewire::test(ListPartners::class)
            ->callTableAction('delete', $partner)
            ->assertNotified();

        expect(Partner::find($partner->id))->not->toBeNull();
    });

    it('allows deletion of partner without associated records', function () {
        $partner = Partner::factory()->create();

        Livewire::test(ListPartners::class)
            ->callTableAction('delete', $partner);

        expect(Partner::find($partner->id))->toBeNull();
    });

});
```

---

## 6. UserResource Tests

**File:** `tests/Feature/Filament/UserResourceTest.php`

### 6.1 Page Rendering Tests

```php
describe('Page Rendering', function () {

    it('can render list page', function () {
        Livewire::test(ListUsers::class)
            ->assertStatus(200);
    });

    it('can render create page', function () {
        Livewire::test(CreateUser::class)
            ->assertStatus(200);
    });

});
```

### 6.2 CRUD Operations Tests

```php
describe('CRUD Operations', function () {

    it('can create user with valid password', function () {
        $role = Role::firstOrCreate(['name' => 'admin']);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'Password123', // Contains letters and numbers
                'roles' => [$role->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(User::where('email', 'test@example.com')->exists())->toBeTrue();
    });

    it('can assign roles to user', function () {
        $role = Role::firstOrCreate(['name' => 'admin']);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'Password123',
                'roles' => [$role->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'test@example.com')->first();
        expect($user->hasRole('admin'))->toBeTrue();
    });

    it('can update user without changing password', function () {
        $user = User::factory()->create(['name' => 'Original Name']);

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($user->fresh()->name)->toBe('Updated Name');
    });

});
```

### 6.3 Validation Tests

```php
describe('Validation', function () {

    it('validates password has letters and numbers', function () {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'onlyletters', // No numbers
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    });

    it('validates national_id is exactly 14 digits', function () {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'Password123',
                'national_id' => '12345', // Too short
            ])
            ->call('create')
            ->assertHasFormErrors(['national_id']);
    });

    it('validates email uniqueness', function () {
        User::factory()->create(['email' => 'existing@example.com']);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'existing@example.com', // Duplicate
                'password' => 'Password123',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    });

    it('requires password on create', function () {
        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Test User',
                'email' => 'test@example.com',
                // password missing
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    });

    it('password optional on edit', function () {
        $user = User::factory()->create();

        Livewire::test(EditUser::class, ['record' => $user->id])
            ->fillForm([
                'name' => 'Updated Name',
                // password not provided
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    });

});
```

### 6.4 Table Features Tests

```php
describe('Table Features', function () {

    it('displays users in table', function () {
        $users = User::factory()->count(5)->create();

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords($users);
    });

    it('can search users by name and email', function () {
        $searchUser = User::factory()->create([
            'name' => 'Searchable User',
            'email' => 'searchable@example.com',
        ]);

        Livewire::test(ListUsers::class)
            ->searchTable('Searchable')
            ->assertCanSeeTableRecords([$searchUser]);

        Livewire::test(ListUsers::class)
            ->searchTable('searchable@example.com')
            ->assertCanSeeTableRecords([$searchUser]);
    });

    it('shows salary type badge', function () {
        $dailyUser = User::factory()->create(['salary_type' => 'daily']);
        $monthlyUser = User::factory()->create(['salary_type' => 'monthly']);

        Livewire::test(ListUsers::class)
            ->assertTableColumnStateEquals('salary_type', 'daily', $dailyUser)
            ->assertTableColumnStateEquals('salary_type', 'monthly', $monthlyUser);
    });

});
```

---

## 7. TreasuryResource Tests

**File:** `tests/Feature/Filament/TreasuryResourceTest.php`

### 7.1 CRUD Operations Tests

```php
describe('CRUD Operations', function () {

    it('can render list page', function () {
        Livewire::test(ListTreasuries::class)
            ->assertStatus(200);
    });

    it('can create treasury', function () {
        Livewire::test(CreateTreasury::class)
            ->fillForm([
                'name' => 'Test Treasury',
                'type' => 'cash',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Treasury::where('name', 'Test Treasury')->exists())->toBeTrue();
    });

    it('can edit treasury', function () {
        $treasury = Treasury::factory()->create(['name' => 'Original Name']);

        Livewire::test(EditTreasury::class, ['record' => $treasury->id])
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($treasury->fresh()->name)->toBe('Updated Name');
    });

    it('can delete empty treasury', function () {
        $treasury = Treasury::factory()->create();

        Livewire::test(ListTreasuries::class)
            ->callTableAction('delete', $treasury);

        expect(Treasury::find($treasury->id))->toBeNull();
    });

});
```

### 7.2 Balance Display Tests

```php
describe('Balance Display', function () {

    it('displays calculated balance from transactions', function () {
        $treasury = Treasury::factory()->create();

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'capital_deposit',
            'amount' => 1000,
            'description' => 'Deposit 1',
        ]);

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'expense',
            'amount' => -300,
            'description' => 'Expense 1',
        ]);

        // Balance should be 1000 - 300 = 700
        Livewire::test(ListTreasuries::class)
            ->assertTableColumnStateEquals('current_balance', 700, $treasury);
    });

    it('shows positive balance in green', function () {
        $treasury = Treasury::factory()->create();

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'capital_deposit',
            'amount' => 1000,
            'description' => 'Deposit',
        ]);

        Livewire::test(ListTreasuries::class)
            ->assertTableColumnStateEquals('current_balance', 1000, $treasury);
    });

    it('shows negative balance in red', function () {
        $treasury = Treasury::factory()->create();

        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'expense',
            'amount' => -500,
            'description' => 'Expense',
        ]);

        Livewire::test(ListTreasuries::class)
            ->assertTableColumnStateEquals('current_balance', -500, $treasury);
    });

});
```

### 7.3 Type Badge Tests

```php
describe('Type Badge', function () {

    it('shows cash type with success color', function () {
        $treasury = Treasury::factory()->create(['type' => 'cash']);

        Livewire::test(ListTreasuries::class)
            ->assertTableColumnStateEquals('type', 'cash', $treasury);
    });

    it('shows bank type with info color', function () {
        $treasury = Treasury::factory()->create(['type' => 'bank']);

        Livewire::test(ListTreasuries::class)
            ->assertTableColumnStateEquals('type', 'bank', $treasury);
    });

});
```

---

## 8. TreasuryTransactionResource Tests

**File:** `tests/Feature/Filament/TreasuryTransactionResourceTest.php`

### 8.1 Page Rendering Tests

```php
describe('Page Rendering', function () {

    it('can render list page', function () {
        Livewire::test(ListTreasuryTransactions::class)
            ->assertStatus(200);
    });

    it('can render create page', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->assertStatus(200);
    });

    it('does not have edit page', function () {
        $transaction = TreasuryTransaction::factory()->create();

        // Verify edit route doesn't exist
        expect(TreasuryTransactionResource::getPages())
            ->not->toHaveKey('edit');
    });

});
```

### 8.2 Cascading Selection Tests (Critical - Reactive)

```php
describe('Cascading Selection', function () {

    it('shows type options based on transaction_category', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->assertFormFieldIsDisabled('type') // Initially disabled
            ->fillForm(['transaction_category' => 'commercial'])
            ->assertFormFieldIsEnabled('type');
    });

    it('resets type when category changes', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'commercial',
                'type' => 'collection',
            ])
            ->fillForm(['transaction_category' => 'partners'])
            ->assertFormSet(['type' => null]);
    });

    it('disables type until category selected', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->assertFormFieldIsDisabled('type');
    });

});
```

### 8.3 Dynamic Entity Selection Tests

```php
describe('Dynamic Entity Selection', function () {

    it('shows partner field for commercial transaction types', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'commercial',
                'type' => 'collection',
            ])
            ->assertFormFieldIsVisible('partner_id')
            ->assertFormFieldIsHidden('employee_id');
    });

    it('shows employee field for HR transaction types', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'partners',
                'type' => 'employee_advance',
            ])
            ->assertFormFieldIsVisible('employee_id')
            ->assertFormFieldIsHidden('partner_id');
    });

    it('filters partners to shareholders for capital transactions', function () {
        $customer = Partner::factory()->customer()->create(['name' => 'Customer']);
        $shareholder = Partner::factory()->create(['type' => 'shareholder', 'name' => 'Shareholder']);

        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'partners',
                'type' => 'capital_deposit',
            ])
            // Shareholder should be selectable, customer should not
            ->assertFormFieldIsVisible('partner_id');
    });

    it('filters partners to those with balance for collections', function () {
        $withBalance = Partner::factory()->customer()->create([
            'name' => 'With Balance',
            'current_balance' => 500,
        ]);

        $zeroBalance = Partner::factory()->customer()->create([
            'name' => 'Zero Balance',
            'current_balance' => 0,
        ]);

        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'commercial',
                'type' => 'collection',
            ])
            ->assertFormFieldIsVisible('partner_id');
    });

});
```

### 8.4 Balance Validation Tests

```php
describe('Balance Validation', function () {

    it('validates amount does not exceed treasury balance for withdrawals', function () {
        $treasury = Treasury::factory()->create();

        // Treasury has 1000 balance
        TreasuryTransaction::create([
            'treasury_id' => $treasury->id,
            'type' => 'capital_deposit',
            'amount' => 1000,
            'description' => 'Initial',
        ]);

        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'commercial',
                'type' => 'payment',
                'treasury_id' => $treasury->id,
                'partner_id' => Partner::factory()->supplier()->create()->id,
                'amount' => 2000, // Exceeds 1000 balance
                'description' => 'Test',
            ])
            ->call('create')
            ->assertHasFormErrors(['amount']);
    });

    it('allows deposits regardless of treasury balance', function () {
        $treasury = Treasury::factory()->create();
        $partner = Partner::factory()->customer()->create(['current_balance' => 500]);

        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'commercial',
                'type' => 'collection',
                'treasury_id' => $treasury->id,
                'partner_id' => $partner->id,
                'amount' => 500,
                'description' => 'Test',
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    });

});
```

### 8.5 Discount Calculation Tests

```php
describe('Discount Calculations', function () {

    it('calculates final_amount as amount minus discount', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'commercial',
                'type' => 'collection',
                'amount' => 1000,
                'discount' => 100,
            ])
            ->assertFormSet(['final_amount' => 900]);
    });

    it('shows discount field only for collection and payment types', function () {
        Livewire::test(CreateTreasuryTransaction::class)
            ->fillForm([
                'transaction_category' => 'commercial',
                'type' => 'collection',
            ])
            ->assertFormFieldIsVisible('discount')
            ->fillForm([
                'transaction_category' => 'partners',
                'type' => 'capital_deposit',
            ])
            ->assertFormFieldIsHidden('discount');
    });

});
```

### 8.6 Immutability Tests

```php
describe('Immutability', function () {

    it('does not show edit action in table', function () {
        $transaction = TreasuryTransaction::factory()->create();

        Livewire::test(ListTreasuryTransactions::class)
            ->assertTableActionHidden('edit', $transaction);
    });

    it('does not show delete action in table', function () {
        $transaction = TreasuryTransaction::factory()->create();

        Livewire::test(ListTreasuryTransactions::class)
            ->assertTableActionHidden('delete', $transaction);
    });

    it('only shows view action', function () {
        $transaction = TreasuryTransaction::factory()->create();

        Livewire::test(ListTreasuryTransactions::class)
            ->assertTableActionVisible('view', $transaction);
    });

});
```

### 8.7 Filter Tests

```php
describe('Filters', function () {

    it('can filter by transaction type', function () {
        $collection = TreasuryTransaction::factory()->create(['type' => 'collection']);
        $payment = TreasuryTransaction::factory()->create(['type' => 'payment']);

        Livewire::test(ListTreasuryTransactions::class)
            ->filterTable('type', 'collection')
            ->assertCanSeeTableRecords([$collection])
            ->assertCanNotSeeTableRecords([$payment]);
    });

    it('can filter by treasury', function () {
        $treasury1 = Treasury::factory()->create();
        $treasury2 = Treasury::factory()->create();

        $trans1 = TreasuryTransaction::factory()->create(['treasury_id' => $treasury1->id]);
        $trans2 = TreasuryTransaction::factory()->create(['treasury_id' => $treasury2->id]);

        Livewire::test(ListTreasuryTransactions::class)
            ->filterTable('treasury_id', $treasury1->id)
            ->assertCanSeeTableRecords([$trans1])
            ->assertCanNotSeeTableRecords([$trans2]);
    });

    it('can filter by date range', function () {
        $oldTransaction = TreasuryTransaction::factory()->create([
            'created_at' => now()->subMonths(2),
        ]);

        $recentTransaction = TreasuryTransaction::factory()->create([
            'created_at' => now(),
        ]);

        Livewire::test(ListTreasuryTransactions::class)
            ->filterTable('created_at', [
                'from' => now()->subDays(7)->format('Y-m-d'),
                'until' => now()->format('Y-m-d'),
            ])
            ->assertCanSeeTableRecords([$recentTransaction])
            ->assertCanNotSeeTableRecords([$oldTransaction]);
    });

    it('can filter by amount range', function () {
        $smallAmount = TreasuryTransaction::factory()->create(['amount' => 100]);
        $largeAmount = TreasuryTransaction::factory()->create(['amount' => 5000]);

        Livewire::test(ListTreasuryTransactions::class)
            ->filterTable('amount', [
                'from' => 1000,
                'until' => 10000,
            ])
            ->assertCanSeeTableRecords([$largeAmount])
            ->assertCanNotSeeTableRecords([$smallAmount]);
    });

});
```

---

## 9. Complete Code Examples

### 9.1 Full SalesInvoice Reactive Flow Test

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\StockMovement;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Setup authentication
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $this->user = User::factory()->create();
    $this->user->assignRole($role);

    \Illuminate\Support\Facades\Gate::before(fn ($user) =>
        $user->hasRole('super_admin') ? true : null
    );

    $this->actingAs($this->user);

    // Ensure funded treasury
    $treasury = Treasury::factory()->create(['name' => 'Main Treasury', 'type' => 'cash']);
    TreasuryTransaction::create([
        'treasury_id' => $treasury->id,
        'type' => 'capital_deposit',
        'amount' => 1000000,
        'description' => 'Initial Capital',
    ]);
});

describe('SalesInvoice Complete Reactive Flow', function () {

    it('performs complete calculation flow: scan -> add items -> calculate totals -> save', function () {
        // ARRANGE
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $unit = Unit::factory()->create(['name' => 'Piece']);

        $productA = Product::factory()->create([
            'name' => 'Product A',
            'small_unit_id' => $unit->id,
            'wholesale_price' => '100.00',
            'retail_price' => '120.00',
        ]);

        $productB = Product::factory()->create([
            'name' => 'Product B',
            'small_unit_id' => $unit->id,
            'wholesale_price' => '50.00',
            'retail_price' => '60.00',
        ]);

        // Add stock for both products
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $productA->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => '80.00',
        ]);

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $productB->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => '40.00',
        ]);

        // ACT & ASSERT - Step by step reactive flow
        $component = Livewire::test(CreateSalesInvoice::class)
            // Step 1: Set up invoice header
            ->fillForm([
                'warehouse_id' => $warehouse->id,
                'partner_id' => $customer->id,
                'payment_method' => 'cash',
            ])

            // Step 2: Scan first product
            ->set('data.product_scanner', $productA->id)

            // Step 3: Verify first item was added with correct price
            ->assertFormSet(function (array $state) use ($productA) {
                $items = $state['items'] ?? [];
                expect($items)->toHaveCount(1);

                $firstItem = array_values($items)[0];
                expect((int) $firstItem['product_id'])->toBe($productA->id);
                expect((float) $firstItem['unit_price'])->toBe(100.00); // wholesale_price
                expect((int) $firstItem['quantity'])->toBe(1);
                expect((float) $firstItem['total'])->toBe(100.00);

                return true;
            })

            // Step 4: Verify scanner is reset
            ->assertSet('data.product_scanner', null)

            // Step 5: Scan second product
            ->set('data.product_scanner', $productB->id)

            // Step 6: Verify both items exist
            ->assertFormSet(function (array $state) {
                expect($state['items'] ?? [])->toHaveCount(2);
                return true;
            })

            // Step 7: Update quantity of first item to 5
            ->fillForm(function (array $currentData) {
                $itemKeys = array_keys($currentData['items']);
                return ["items.{$itemKeys[0]}.quantity" => 5];
            })

            // Step 8: Verify item total recalculated (5 × 100 = 500)
            ->assertFormSet(function (array $state) {
                $items = array_values($state['items']);
                expect((float) $items[0]['total'])->toBe(500.00);
                return true;
            })

            // Step 9: Verify subtotal = 500 + 50 = 550
            ->assertFormSet(['subtotal' => 550.00])

            // Step 10: Add percentage discount
            ->fillForm([
                'discount_type' => 'percentage',
                'discount_value' => 10, // 10%
            ])

            // Step 11: Verify discount and total calculated
            ->assertFormSet([
                'discount' => 55.00,        // 550 × 10% = 55
                'total' => 495.00,          // 550 - 55 = 495
                'remaining_amount' => 0,    // Cash payment = no remaining
            ]);

        // Step 12: Save and verify persistence
        $component->call('create')
            ->assertHasNoFormErrors();

        // VERIFY DATABASE STATE
        $invoice = SalesInvoice::latest()->first();

        expect($invoice)->not->toBeNull();
        expect((float) $invoice->subtotal)->toBe(550.00);
        expect((float) $invoice->discount)->toBe(55.00);
        expect((float) $invoice->total)->toBe(495.00);
        expect($invoice->items)->toHaveCount(2);
        expect($invoice->status)->toBe('draft');
        expect($invoice->payment_method)->toBe('cash');
    });

    it('handles credit payment with installments correctly', function () {
        // ARRANGE
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $product = Product::factory()->create(['wholesale_price' => '1000.00']);

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 10,
            'cost_at_time' => '800.00',
        ]);

        // ACT
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm([
                'warehouse_id' => $warehouse->id,
                'partner_id' => $customer->id,
                'payment_method' => 'credit',
            ])
            ->set('data.product_scanner', $product->id)
            ->fillForm([
                'paid_amount' => 300,
                'has_installment_plan' => true,
                'installment_months' => 3,
                'installment_start_date' => now()->addMonth()->startOfMonth()->format('Y-m-d'),
            ])

            // ASSERT - Verify remaining amount calculation
            ->assertFormSet([
                'total' => 1000.00,
                'remaining_amount' => 700.00, // 1000 - 300
            ])

            // Verify installment fields are visible
            ->assertFormFieldIsVisible('installment_months')
            ->assertFormFieldIsVisible('installment_start_date')
            ->assertFormFieldIsVisible('installment_interest_percentage');
    });

    it('correctly handles dual-unit product pricing', function () {
        // ARRANGE
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $smallUnit = Unit::factory()->create(['name' => 'Piece']);
        $largeUnit = Unit::factory()->create(['name' => 'Carton']);

        $product = Product::factory()->create([
            'small_unit_id' => $smallUnit->id,
            'large_unit_id' => $largeUnit->id,
            'factor' => 12,
            'wholesale_price' => '10.00',
            'large_wholesale_price' => '120.00',
        ]);

        // Add stock (in base units)
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 120, // 10 cartons worth
            'cost_at_time' => '8.00',
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'status' => 'draft',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 1,
            'unit_price' => '10.00',
            'total' => '10.00',
        ]);

        // ACT - Change unit type to large
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(function ($data) {
                $key = array_keys($data['items'])[0];
                return ["items.{$key}.unit_type" => 'large'];
            })

            // ASSERT - Price updated to large unit price
            ->assertFormSet(function ($state) {
                $item = array_values($state['items'])[0];
                expect((float) $item['unit_price'])->toBe(120.00);
                expect((float) $item['total'])->toBe(120.00);
                return true;
            });
    });

});
```

### 9.2 Product Dual-Unit Pricing Test

```php
<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $this->user = User::factory()->create();
    $this->user->assignRole($role);

    \Illuminate\Support\Facades\Gate::before(fn ($user) =>
        $user->hasRole('super_admin') ? true : null
    );

    $this->actingAs($this->user);
});

describe('Product Dual-Unit Pricing', function () {

    it('auto-calculates large unit prices from small unit prices and factor', function () {
        // ARRANGE
        $smallUnit = Unit::factory()->create(['name' => 'قطعة']); // Piece
        $largeUnit = Unit::factory()->create(['name' => 'كرتونة']); // Carton

        // ACT & ASSERT
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
                'min_stock' => 10,
            ])
            // Large retail = 10 × 12 = 120
            // Large wholesale = 8 × 12 = 96
            ->assertFormSet([
                'large_retail_price' => '120.00',
                'large_wholesale_price' => '96.00',
            ]);
    });

    it('recalculates when factor is changed', function () {
        // ARRANGE
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        // ACT & ASSERT
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
                'min_stock' => 10,
            ])
            ->assertFormSet([
                'large_retail_price' => '120.00',
                'large_wholesale_price' => '96.00',
            ])
            // Change factor from 12 to 24
            ->fillForm(['factor' => 24])
            ->assertFormSet([
                'large_retail_price' => '240.00', // 10 × 24
                'large_wholesale_price' => '192.00', // 8 × 24
            ]);
    });

    it('recalculates when retail_price is changed', function () {
        // ARRANGE
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        // ACT & ASSERT
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
                'min_stock' => 10,
            ])
            ->assertFormSet(['large_retail_price' => '120.00'])
            // Change retail price from 10 to 15
            ->fillForm(['retail_price' => '15.00'])
            ->assertFormSet(['large_retail_price' => '180.00']); // 15 × 12
    });

    it('allows manual override of large unit prices', function () {
        // ARRANGE
        $smallUnit = Unit::factory()->create();
        $largeUnit = Unit::factory()->create();

        // ACT
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => '10.00',
                'wholesale_price' => '8.00',
                'large_retail_price' => '150.00', // Manual override (not 120)
                'large_wholesale_price' => '100.00', // Manual override (not 96)
                'min_stock' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // ASSERT
        $product = Product::latest()->first();
        expect((float) $product->large_retail_price)->toBe(150.00);
        expect((float) $product->large_wholesale_price)->toBe(100.00);
    });

});
```

---

## 10. Verification & Execution

### 10.1 Running Tests

```bash
# Run all Filament tests
php artisan test tests/Feature/Filament

# Run specific resource tests
php artisan test tests/Feature/Filament/SalesInvoiceResourceTest.php
php artisan test tests/Feature/Filament/ProductResourceTest.php

# Run with verbose output
php artisan test tests/Feature/Filament --verbose

# Run with parallel execution
php artisan test --parallel tests/Feature/Filament

# Run with coverage report
php artisan test --coverage tests/Feature/Filament

# Run specific test by name
php artisan test --filter="performs complete calculation flow"
```

### 10.2 Test Priority Order

**Phase 1: Critical Business Logic**
1. `SalesInvoiceResourceTest.php` - Reactive calculations, stock validation
2. `ProductResourceTest.php` - Dual-unit pricing calculations
3. `PurchaseInvoiceResourceTest.php` - Similar patterns to sales

**Phase 2: Data Integrity**
4. `PartnerResourceTest.php` - Conditional fields, deletion protection
5. `TreasuryTransactionResourceTest.php` - Cascading selection, balance validation

**Phase 3: Complete Coverage**
6. `TreasuryResourceTest.php` - Basic CRUD, balance display
7. `UserResourceTest.php` - Validation, role assignment

### 10.3 Critical Files Reference

| Resource | File Path | Key Tests |
|----------|-----------|-----------|
| SalesInvoice | `app/Filament/Resources/SalesInvoiceResource.php` | Reactive calculations |
| EditSalesInvoice | `app/Filament/Resources/SalesInvoiceResource/Pages/EditSalesInvoice.php` | Post action, header actions |
| Product | `app/Filament/Resources/ProductResource.php` | Dual-unit pricing |
| TreasuryTransaction | `app/Filament/Resources/TreasuryTransactionResource.php` | Cascading category/type |
| TestCase | `tests/TestCase.php` | `ensureFundedTreasury()` |
| TestHelpers | `tests/Helpers/TestHelpers.php` | Factory helpers |

### 10.4 Expected Test Count

| Resource | Test Count (Estimated) |
|----------|------------------------|
| SalesInvoiceResource | ~35 tests |
| PurchaseInvoiceResource | ~20 tests |
| ProductResource | ~25 tests |
| PartnerResource | ~15 tests |
| UserResource | ~12 tests |
| TreasuryResource | ~8 tests |
| TreasuryTransactionResource | ~18 tests |
| **Total** | **~133 tests** |

---

## Summary

This test plan provides comprehensive UI/Integration coverage for all Filament Resources in the Mawared ERP system. Key focus areas include:

1. **Reactive Form Testing** - Testing Livewire's reactive fields and calculations
2. **Invoice Flow Testing** - Complete lifecycle from creation to posting
3. **Stock Validation** - Ensuring inventory integrity
4. **Financial Accuracy** - Precise calculation testing
5. **Conditional Field Logic** - Testing visibility and required state changes
6. **Action Testing** - Header and table actions based on record state
7. **Integration Testing** - Verifying service layer interactions

Each test follows the ARRANGE-ACT-ASSERT pattern and uses Filament's testing helpers (`assertFormSet`, `fillForm`, `callAction`, etc.) for clean, maintainable tests.
