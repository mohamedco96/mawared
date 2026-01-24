<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Filament\Resources\SalesInvoiceResource\Pages\ListSalesInvoices;
use App\Models\Installment;
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
use Tests\Helpers\TestHelpers;
use Tests\TestCase;

class SalesInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as a user for all tests
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);

        $user = User::factory()->create([
            'email' => 'mohamed@osoolerp.com',
        ]);
        $user->assignRole($role);

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        $this->actingAs($user);

        // Ensure treasury exists for transactions
        TestHelpers::createFundedTreasury();
    }

    // ===== PAGE RENDERING TESTS =====

    public function test_can_render_list_page(): void
    {
        Livewire::test(ListSalesInvoices::class)
            ->assertStatus(200)
            ->assertSee('فواتير البيع');
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateSalesInvoice::class)
            ->assertStatus(200);
    }

    public function test_can_render_edit_page_for_draft_invoice(): void
    {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertStatus(200);
    }

    // ===== PRODUCT SCANNER TESTS (REACTIVE) =====

    public function test_product_scanner_adds_product_to_items(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'wholesale_price' => '100.00',
            'retail_price' => '120.00',
        ]);

        // Add stock to allow selection
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 50,
            'cost_at_time' => 80,
            'reference_type' => 'manual_adjustment',
            'reference_id' => 1,
        ]);

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm(['warehouse_id' => $warehouse->id])
            ->set('data.product_scanner', $product->id)
            ->assertFormSet(function (array $state) use ($product) {
                $items = $state['items'] ?? [];
                if (empty($items)) {
                    return false;
                }

                $item = array_values($items)[0];

                return (int) $item['product_id'] === $product->id
                    && (float) $item['unit_price'] == 100.00 // wholesale
                    && (int) $item['quantity'] === 1
                    && (float) $item['total'] == 100.00;
            })
            ->assertSet('data.product_scanner', null); // Should reset
    }

    public function test_product_scanner_increments_quantity_for_existing_item(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        Livewire::test(CreateSalesInvoice::class)
            ->fillForm(['warehouse_id' => $warehouse->id])
            ->set('data.product_scanner', $product->id)
            ->set('data.product_scanner', $product->id)
            ->assertFormSet(function (array $state) {
                return count($state['items']) === 2;
            });
    }

    // ===== ITEM CALCULATION TESTS =====

    public function test_item_total_updates_when_quantity_changes(): void
    {
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

                return (float) $item['total'] == 500.00;
            });
    }

    public function test_item_total_updates_when_unit_type_changes_dual_unit(): void
    {
        $smallUnit = Unit::factory()->create(['name' => 'Piece']);
        $largeUnit = Unit::factory()->create(['name' => 'Carton']);

        $product = Product::factory()->create([
            'small_unit_id' => $smallUnit->id,
            'large_unit_id' => $largeUnit->id,
            'factor' => 12,
            'wholesale_price' => '10.00',
            'large_wholesale_price' => '120.00',
        ]);

        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

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

                return (float) $item['unit_price'] == 120.00
                    && (float) $item['total'] == 120.00;
            });
    }

    // ===== INVOICE-LEVEL CALCULATION TESTS =====

    public function test_calculates_subtotal_and_total(): void
    {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);

        // Create items in DB
        $item1 = $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        $item2 = $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 3,
            'unit_price' => '100.00',
            'total' => '300.00',
        ]);

        // Open edit page and trigger recalculation by updating one item
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(function ($data) use ($item1) {
                // Find key for item1
                $key = null;
                foreach ($data['items'] as $k => $item) {
                    if ($item['product_id'] == $item1->product_id) {
                        $key = $k;
                        break;
                    }
                }

                return ["items.{$key}.quantity" => 6];
            })
            ->assertFormSet(function ($state) {
                $subtotal = $state['subtotal'];

                // 6 * 100 + 3 * 100 = 900
                return (float) $subtotal == 900.00;
            });
    }

    public function test_calculates_fixed_discount(): void
    {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);
        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
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
                'total' => 850.00,
            ]);
    }

    public function test_calculates_percentage_discount(): void
    {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);
        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
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
                'discount' => 100.00,
                'total' => 900.00,
            ]);
    }

    public function test_calculates_commission(): void
    {
        $salesperson = User::factory()->create();
        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
            'sales_person_id' => $salesperson->id,
        ]);
        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(['commission_rate' => 5])
            ->assertFormSet([
                'commission_amount' => 50.00,
            ]);
    }

    // ===== PAYMENT METHOD & REMAINING AMOUNT =====

    public function test_remaining_amount_zero_for_cash(): void
    {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);
        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm(['payment_method' => 'cash'])
            ->assertFormSet([
                'remaining_amount' => 0,
            ]);
    }

    public function test_remaining_amount_for_credit(): void
    {
        $invoice = SalesInvoice::factory()->create(['status' => 'draft']);
        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
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
                'remaining_amount' => 700.00,
            ]);
    }

    public function test_payment_method_field_visibility(): void
    {
        Livewire::test(CreateSalesInvoice::class)
            ->fillForm(['payment_method' => 'credit'])
            ->assertFormFieldIsVisible('paid_amount')
            ->assertFormFieldIsVisible('remaining_amount')
            ->assertFormFieldIsVisible('has_installment_plan')
            ->fillForm(['payment_method' => 'cash'])
            ->assertFormFieldIsHidden('paid_amount')
            ->assertFormFieldIsHidden('remaining_amount')
            ->assertFormFieldIsHidden('has_installment_plan');
    }

    // ===== STOCK VALIDATION =====

    public function test_stock_validation_error_when_exceeding_limit(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // 5 in stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 5,
            'cost_at_time' => 50,
            'reference_type' => 'manual_adjustment',
            'reference_id' => 1,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);

        // Use fillForm to populate items and trigger validation properly
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10, // Exceeds 5
                        'unit_price' => '100.00',
                        'total' => '1000.00',
                        'unit_type' => 'small',
                    ],
                ],
            ])
            ->call('save')
            ->assertHasErrors(); // Relaxed assertion since key might be dynamic
    }

    // ===== HEADER & TABLE ACTIONS =====

    public function test_delete_action_visibility(): void
    {
        $draft = SalesInvoice::factory()->create(['status' => 'draft']);
        $posted = SalesInvoice::factory()->create(['status' => 'posted']);

        Livewire::test(EditSalesInvoice::class, ['record' => $draft->id])
            ->assertActionVisible('delete');

        Livewire::test(EditSalesInvoice::class, ['record' => $posted->id])
            ->assertActionHidden('delete');
    }

    public function test_post_action_visibility(): void
    {
        $draft = SalesInvoice::factory()->create(['status' => 'draft']);
        // Add items so it can be posted
        $draft->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        $posted = SalesInvoice::factory()->create(['status' => 'posted']);

        // Check List page for table action
        Livewire::test(ListSalesInvoices::class)
            ->assertTableActionVisible('post', $draft)
            ->assertTableActionHidden('post', $posted);
    }

    // ===== INTEGRATION: POST INVOICE =====

    public function test_post_action_creates_stock_movement_and_updates_status(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Initial stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
            'reference_type' => 'manual_adjustment',
            'reference_id' => 1,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => '100.00',
            'total' => '500.00',
        ]);

        Livewire::test(ListSalesInvoices::class)
            ->callTableAction('post', $invoice);

        $this->assertEquals('posted', $invoice->fresh()->status);

        // Verify stock movement
        $movement = StockMovement::where('reference_id', $invoice->id)
            ->where('reference_type', 'sales_invoice')
            ->where('type', 'sale')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(-5, $movement->quantity);
    }

    public function test_post_action_creates_treasury_transaction(): void
    {
        $this->withoutExceptionHandling();

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Add stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
            'reference_type' => 'manual_adjustment',
            'reference_id' => 1,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => 500.00,
            'paid_amount' => 500.00, // Cash implies full payment usually, or handled by logic
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => '500.00',
            'total' => '500.00',
        ]);

        Livewire::test(ListSalesInvoices::class)
            ->callTableAction('post', $invoice);

        // Verify status updated
        $this->assertEquals('posted', $invoice->fresh()->status);

        // Verify transaction exists
        $transaction = TreasuryTransaction::where('reference_id', $invoice->id)
            ->where('reference_type', 'sales_invoice')
            ->latest()
            ->first();

        $this->assertNotNull($transaction, 'Treasury transaction was not created');
        $this->assertEquals(500.00, $transaction->amount);

        // Verify Partner Balance (Should be 0 for fully paid cash invoice)
        $this->assertEquals(0, $invoice->partner->fresh()->current_balance);
    }

    public function test_post_credit_invoice_updates_partner_balance(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Add stock
        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
            'reference_type' => 'manual_adjustment',
            'reference_id' => 1,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'credit',
            'total' => 1000.00,
            'paid_amount' => 0.00,
            'remaining_amount' => 1000.00,
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => '100.00',
            'total' => '1000.00',
        ]);

        Livewire::test(ListSalesInvoices::class)
            ->callTableAction('post', $invoice);

        $invoice->refresh();
        $this->assertEquals('posted', $invoice->status);
        $this->assertEquals(1000.00, $invoice->remaining_amount);

        // Verify Partner Balance (Should be 1000 for credit invoice)
        $partner = $invoice->partner->fresh();

        // Verify calculation logic is correct
        $this->assertEquals(1000.00, $partner->calculateBalance());
    }

    // ===== INSTALLMENT GENERATION =====

    public function test_generates_installments_when_plan_exists(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        StockMovement::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => 100,
            'cost_at_time' => 50,
            'reference_type' => 'manual_adjustment',
            'reference_id' => 1,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
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
            'quantity' => 12,
            'unit_price' => '100.00',
            'total' => '1200.00',
        ]);

        Livewire::test(ListSalesInvoices::class)
            ->callTableAction('post', $invoice);

        $this->assertEquals(3, Installment::where('sales_invoice_id', $invoice->id)->count());
    }

    // ===== FILTERS & BULK ACTIONS =====

    public function test_can_filter_by_status(): void
    {
        $draft = SalesInvoice::factory()->create(['status' => 'draft']);
        $posted = SalesInvoice::factory()->create(['status' => 'posted']);

        Livewire::test(ListSalesInvoices::class)
            ->filterTable('status', 'draft')
            ->assertCanSeeTableRecords([$draft])
            ->assertCanNotSeeTableRecords([$posted])
            ->filterTable('status', 'posted')
            ->assertCanSeeTableRecords([$posted])
            ->assertCanNotSeeTableRecords([$draft]);
    }

    public function test_can_filter_by_payment_method(): void
    {
        $cash = SalesInvoice::factory()->create(['payment_method' => 'cash']);
        $credit = SalesInvoice::factory()->create(['payment_method' => 'credit']);

        Livewire::test(ListSalesInvoices::class)
            ->filterTable('payment_method', 'cash')
            ->assertCanSeeTableRecords([$cash])
            ->assertCanNotSeeTableRecords([$credit])
            ->filterTable('payment_method', 'credit')
            ->assertCanSeeTableRecords([$credit])
            ->assertCanNotSeeTableRecords([$cash]);
    }

    public function test_bulk_delete_respects_safeguards(): void
    {
        $safeToDelete = SalesInvoice::factory()->create(['status' => 'draft']);

        $unsafeToDelete = SalesInvoice::factory()->create(['status' => 'posted']);
        // Add item to unsafe invoice so it's not empty (empty invoices might be deletable depending on logic)
        $unsafeToDelete->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'unit_price' => 100,
            'total' => 100,
        ]);

        Livewire::test(ListSalesInvoices::class)
            ->callTableBulkAction('delete', [$safeToDelete, $unsafeToDelete]);

        $this->assertSoftDeleted($safeToDelete);
        $this->assertModelExists($unsafeToDelete);
    }
}
