<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\PurchaseInvoiceResource\Pages\CreatePurchaseInvoice;
use App\Filament\Resources\PurchaseInvoiceResource\Pages\EditPurchaseInvoice;
use App\Filament\Resources\PurchaseInvoiceResource\Pages\ListPurchaseInvoices;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Helpers\TestHelpers;
use Tests\TestCase;

class PurchaseInvoiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Authenticate as a user for all tests
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin']);

        $user = User::factory()->create();
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
        Livewire::test(ListPurchaseInvoices::class)
            ->assertStatus(200)
            ->assertSee('فواتير الشراء');
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreatePurchaseInvoice::class)
            ->assertStatus(200);
    }

    public function test_can_render_edit_page_for_draft_invoice(): void
    {
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->assertStatus(200);
    }

    // ===== ITEM CALCULATION TESTS =====

    public function test_item_total_updates_when_quantity_changes(): void
    {
        $product = Product::factory()->create(['avg_cost' => '50.00']);
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        $invoice->items()->create([
            'product_id' => $product->id,
            'unit_type' => 'small',
            'quantity' => 1,
            'unit_cost' => '50.00',
            'total' => '500.00', // Intentional wrong total to see update
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->fillForm(function ($data) {
                $key = array_keys($data['items'])[0];

                return ["items.{$key}.quantity" => 5];
            })
            ->assertFormSet(function ($state) {
                $item = array_values($state['items'])[0];

                // 5 * 50 = 250
                return (float) $item['total'] == 250.00;
            });
    }

    // ===== INVOICE-LEVEL CALCULATION TESTS =====

    public function test_calculates_subtotal_and_total(): void
    {
        $invoice = PurchaseInvoice::factory()->create(['status' => 'draft']);

        $item1 = $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 2,
            'unit_cost' => '100.00',
            'total' => '200.00',
        ]);

        $item2 = $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'unit_type' => 'small',
            'quantity' => 3,
            'unit_cost' => '100.00',
            'total' => '300.00',
        ]);

        Livewire::test(EditPurchaseInvoice::class, ['record' => $invoice->id])
            ->fillForm(function ($data) use ($item1) {
                $key = null;
                foreach ($data['items'] as $k => $item) {
                    if ($item['product_id'] == $item1->product_id) {
                        $key = $k;
                        break;
                    }
                }

                return ["items.{$key}.quantity" => 4];
            })
            ->assertFormSet(function ($state) {
                $subtotal = $state['subtotal'];

                // 4 * 100 + 3 * 100 = 700
                return (float) $subtotal == 700.00;
            });
    }

    // ===== INTEGRATION: POST INVOICE =====

    public function test_post_action_creates_stock_movement_and_updates_status(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $invoice = PurchaseInvoice::factory()->create([
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
            'payment_method' => 'cash',
        ]);

        $invoice->items()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => '50.00',
            'total' => '500.00',
        ]);

        Livewire::test(ListPurchaseInvoices::class)
            ->callTableAction('post', $invoice);

        $this->assertEquals('posted', $invoice->fresh()->status);

        // Verify stock movement
        $movement = StockMovement::where('reference_id', $invoice->id)
            ->where('reference_type', 'purchase_invoice')
            ->where('type', 'purchase')
            ->first();

        $this->assertNotNull($movement);
        $this->assertEquals(10, $movement->quantity);
    }

    public function test_post_action_creates_treasury_transaction(): void
    {
        $this->withoutExceptionHandling();

        $invoice = PurchaseInvoice::factory()->create([
            'status' => 'draft',
            'payment_method' => 'cash',
            'total' => 500.00,
            'paid_amount' => 500.00,
        ]);

        $invoice->items()->create([
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'unit_cost' => '500.00',
            'total' => '500.00',
        ]);

        Livewire::test(ListPurchaseInvoices::class)
            ->callTableAction('post', $invoice);

        $this->assertEquals('posted', $invoice->fresh()->status);

        // Verify transaction exists
        $transaction = TreasuryTransaction::where('reference_id', $invoice->id)
            ->where('reference_type', 'purchase_invoice')
            ->latest()
            ->first();

        $this->assertNotNull($transaction, 'Treasury transaction was not created');
        $this->assertEquals(-500.00, $transaction->amount);
        $this->assertEquals('payment', $transaction->type); // Purchase is payment (withdrawal)
    }
}
