<?php

namespace Tests\Feature;

use App\Filament\Resources\PurchaseInvoiceResource\Pages\CreatePurchaseInvoice;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PurchaseInvoiceCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculations_update_totals_correctly()
    {
        // Setup
        $user = \App\Models\User::factory()->create();

        // Grant permissions
        $permissions = [
            'view_any_purchase::invoice',
            'view_purchase::invoice',
            'create_purchase::invoice',
        ];

        foreach ($permissions as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission]);
            $user->givePermissionTo($permission);
        }

        $this->actingAs($user);

        $warehouse = Warehouse::factory()->create(['is_active' => true]);
        $partner = Partner::factory()->create(['type' => 'supplier']);
        $product = Product::factory()->create([
            'avg_cost' => 100,
            'retail_price' => 150,
        ]);

        // Start the component
        $component = Livewire::test(CreatePurchaseInvoice::class);

        $component->assertSuccessful();

        $component->set('data.warehouse_id', $warehouse->id)
            ->set('data.partner_id', $partner->id)
            ->set('data.payment_method', 'cash');

        // Manually add an item to the repeater
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $component->set('data.items', [
            $uuid => [
                'product_id' => null,
                'unit_type' => 'small',
                'quantity' => 1,
                'unit_cost' => 0,
                'total' => 0,
            ],
        ]);

        // Get the UUID (redundant but keeps variable for following code)
        $items = $component->get('data.items');
        $this->assertNotEmpty($items);
        $uuid = array_key_first($items);

        // Set product_id which should trigger defaults (unit_cost, etc) if hooked
        $component->set("data.items.{$uuid}.product_id", $product->id);

        // Manually trigger the afterStateUpdated for product_id if needed,
        // but Livewire::set usually triggers lifecycle if wired properly.
        // However, in tests, sometimes we need to be explicit if using `set`.
        // The resource uses `->live(onBlur: true)->afterStateUpdated(...)`.
        // In Livewire tests, `set` should trigger updates.

        // Verify defaults
        $component->assertSet("data.items.{$uuid}.unit_cost", 100);
        $component->assertSet("data.items.{$uuid}.quantity", 1);
        $component->assertSet("data.items.{$uuid}.total", 100);

        // Now change quantity
        $component->set("data.items.{$uuid}.quantity", 2);

        // Check row total
        $component->assertSet("data.items.{$uuid}.total", 200);

        // Check invoice subtotal and total
        // This is where we expect failure if recalculateTotals fails to see the items
        $component->assertSet('data.subtotal', 200);
        $component->assertSet('data.total', 200);

        // Change unit cost
        $component->set("data.items.{$uuid}.unit_cost", 50);

        // Check row total
        $component->assertSet("data.items.{$uuid}.total", 100); // 50 * 2

        // Check invoice total
        $component->assertSet('data.subtotal', 100);
        $component->assertSet('data.total', 100);
    }
}
