<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\SalesReturn;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SalesInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as a user for all tests
        // Create super_admin role
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);
        
        // Create authorized user
        $user = User::factory()->create([
            'email' => 'mohamed@osoolerp.com',
        ]);
        $user->assignRole($role);
        
        // Grant all permissions to super_admin
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });
        
        $this->actingAs($user);
    }

    // ===== DRAFT STATE TESTS =====

    public function test_delete_action_is_visible_on_draft_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
        ]);

        // ACT & ASSERT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertActionVisible('delete');
    }

    public function test_create_return_action_is_hidden_on_draft_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
        ]);

        // ACT & ASSERT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('create_return');
    }

    public function test_form_fields_are_enabled_on_draft_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
        ]);

        // ACT & ASSERT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertFormFieldIsEnabled('status')
            ->assertFormFieldIsEnabled('warehouse_id')
            ->assertFormFieldIsEnabled('partner_id')
            ->assertFormFieldIsEnabled('payment_method')
            ->assertFormFieldIsEnabled('discount_value')
            ->assertFormFieldIsEnabled('notes');
    }

    // ===== POSTED STATE TESTS =====

    public function test_delete_action_is_hidden_on_posted_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
        ]);

        // ACT & ASSERT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertActionHidden('delete');
    }

    public function test_create_return_action_is_visible_on_posted_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
        ]);

        // ACT & ASSERT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertActionVisible('create_return');
    }

    public function test_form_fields_are_disabled_on_posted_invoice(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
        ]);

        // ACT & ASSERT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->assertFormFieldIsDisabled('status')
            ->assertFormFieldIsDisabled('warehouse_id')
            ->assertFormFieldIsDisabled('partner_id')
            ->assertFormFieldIsDisabled('payment_method')
            ->assertFormFieldIsDisabled('discount_value')
            ->assertFormFieldIsDisabled('notes');
    }

    // ===== CREATE RETURN ACTION TESTS =====

    public function test_create_return_action_creates_sales_return_with_same_data(): void
    {
        // ARRANGE
        $unit = Unit::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $customer = Partner::factory()->customer()->create();
        $product = Product::factory()->create([
            'small_unit_id' => $unit->id,
        ]);

        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'warehouse_id' => $warehouse->id,
            'partner_id' => $customer->id,
            'payment_method' => 'cash',
            'subtotal' => '1000.00',
            'discount' => '100.00',
            'total' => '900.00',
            'notes' => 'Test invoice notes',
        ]);

        SalesInvoiceItem::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 10,
            'unit_price' => '100.00',
            'discount' => '0.00',
            'total' => '1000.00',
        ]);

        // ACT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->callAction('create_return');

        // ASSERT
        $this->assertEquals(1, SalesReturn::count());

        $return = SalesReturn::first();
        $this->assertEquals($warehouse->id, $return->warehouse_id);
        $this->assertEquals($customer->id, $return->partner_id);
        $this->assertEquals('draft', $return->status);
        $this->assertEquals('cash', $return->payment_method);
        $this->assertEquals('1000.0000', $return->subtotal);
        $this->assertEquals('100.0000', $return->discount);
        $this->assertEquals('900.0000', $return->total);
        $this->assertStringContainsString($invoice->invoice_number, $return->notes);

        // Verify items were replicated
        $this->assertEquals(1, $return->items()->count());
        $returnItem = $return->items()->first();
        $this->assertEquals($product->id, $returnItem->product_id);
        $this->assertEquals('small', $returnItem->unit_type);
        $this->assertEquals(10, $returnItem->quantity);
        $this->assertEquals('100.0000', $returnItem->unit_price);
        $this->assertEquals('0.0000', $returnItem->discount);
        $this->assertEquals('1000.0000', $returnItem->total);
    }

    // ===== LIVE CALCULATION TESTS =====

    public function test_item_total_updates_when_quantity_changes(): void
    {
        // ARRANGE
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'small_unit_id' => $unit->id,
            'retail_price' => '100.00',
        ]);

        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
        ]);

        SalesInvoiceItem::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'discount' => '0.00',
            'total' => '500.00',
        ]);

        // ACT & ASSERT
        // First fill the form with product_id to avoid the itemLabel error
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'items' => [
                    [
                        'product_id' => $product->id,
                        'unit_type' => 'small',
                        'quantity' => 10, // Changed from 5 to 10
                        'unit_price' => '100.00',
                        'discount' => '0.00',
                    ]
                ]
            ])
            ->assertFormSet([
                'items.0.total' => 1000.00, // 10 * 100
            ]);
    }

    public function test_item_total_updates_when_unit_price_changes(): void
    {
        // ARRANGE
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'small_unit_id' => $unit->id,
            'retail_price' => '100.00',
        ]);

        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
        ]);

        SalesInvoiceItem::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'discount' => '0.00',
            'total' => '500.00',
        ]);

        // ACT & ASSERT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'items' => [
                    [
                        'product_id' => $product->id,
                        'unit_type' => 'small',
                        'quantity' => 5,
                        'unit_price' => '150.00', // Changed from 100 to 150
                        'discount' => '0.00',
                    ]
                ]
            ])
            ->assertFormSet([
                'items.0.total' => 750.00, // 5 * 150
            ]);
    }



    public function test_totals_persist_correctly_on_save(): void
    {
        // ARRANGE
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'small_unit_id' => $unit->id,
            'retail_price' => '100.00',
        ]);

        $invoice = SalesInvoice::factory()->create([
            'status' => 'draft',
            'subtotal' => '0.00',
            'discount' => '0.00',
            'total' => '0.00',
        ]);
        
        // Add sufficient stock
        \App\Models\StockMovement::create([
            'warehouse_id' => $invoice->warehouse_id,
            'product_id' => $product->id,
            'quantity' => 100,
            'cost_at_time' => 0,
            'type' => 'correction',
            'reference_type' => 'sales_invoice',
            'reference_id' => $invoice->id,
        ]);

        SalesInvoiceItem::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 5,
            'unit_price' => '100.00',
            'discount' => '50.00',
            'total' => '450.00',
        ]);

        SalesInvoiceItem::factory()->create([
            'sales_invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 3,
            'unit_price' => '200.00',
            'discount' => '0.00',
            'total' => '600.00',
        ]);

        // ACT
        Livewire::test(EditSalesInvoice::class, ['record' => $invoice->id])
            ->fillForm([
                'discount_value' => '100.00',
                'discount' => '100.00', // Manually sync hidden field since hooks don't run in tests
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        // ASSERT
        $invoice->refresh();
        // Subtotal should be sum of item totals: 450 + 600 = 1050
        $this->assertEquals('1050.0000', $invoice->subtotal);
        // Total should be subtotal - discount: 1050 - 100 = 950
        $this->assertEquals('950.0000', $invoice->total);
    }

    // ===== MODEL-LEVEL PROTECTION TESTS =====

    public function test_cannot_delete_posted_invoice_through_model(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
        ]);

        // ACT & ASSERT
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن حذف فاتورة مؤكدة');
        $invoice->delete();
    }

    public function test_cannot_update_posted_invoice_through_model(): void
    {
        // ARRANGE
        $invoice = SalesInvoice::factory()->create([
            'status' => 'posted',
            'notes' => 'Original notes',
        ]);

        // ACT & ASSERT
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('لا يمكن تعديل فاتورة مؤكدة');
        $invoice->update(['notes' => 'New notes']);
    }
}
