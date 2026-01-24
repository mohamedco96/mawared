<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductResourceTest extends TestCase
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
    }

    // ===== PAGE RENDERING TESTS =====

    public function test_can_render_list_page(): void
    {
        Livewire::test(ListProducts::class)
            ->assertStatus(200)
            ->assertSee('المنتجات');
    }

    public function test_can_render_create_page(): void
    {
        Livewire::test(CreateProduct::class)
            ->assertStatus(200);
    }

    public function test_can_render_edit_page(): void
    {
        $product = Product::factory()->create();

        Livewire::test(EditProduct::class, ['record' => $product->id])
            ->assertStatus(200);
    }

    // ===== DUAL UNIT PRICING TESTS =====

    public function test_calculates_large_unit_prices_when_small_price_changes(): void
    {
        $largeUnit = Unit::factory()->create(['name' => 'Carton']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'large_unit_id' => $largeUnit->id,
                'factor' => 12,
                'retail_price' => 10,
                'wholesale_price' => 8,
            ])
            ->assertFormSet([
                'large_retail_price' => '120.00',
                'large_wholesale_price' => '96.00',
            ]);
    }

    public function test_calculates_large_unit_prices_when_factor_changes(): void
    {
        $largeUnit = Unit::factory()->create(['name' => 'Carton']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'large_unit_id' => $largeUnit->id,
                'retail_price' => 10,
                'wholesale_price' => 8,
                'factor' => 10,
            ])
            ->assertFormSet([
                'large_retail_price' => '100.00',
                'large_wholesale_price' => '80.00',
            ]);
    }

    // ===== AUTO-GENERATION TESTS =====

    public function test_auto_generates_sku_and_barcode(): void
    {
        $smallUnit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Test Product',
                'small_unit_id' => $smallUnit->id,
                'retail_price' => 100,
                'wholesale_price' => 90,
                'min_stock' => 10,
            ])
            ->call('create')
            ->assertHasNoErrors();

        $product = Product::latest()->first();

        $this->assertNotNull($product->sku);
        $this->assertNotNull($product->barcode);
        // $this->assertStringStartsWith('PRD-', $product->sku);
    }

    // ===== VALIDATION TESTS =====

    public function test_validates_unique_sku(): void
    {
        $existing = Product::factory()->create(['sku' => 'TEST-SKU']);
        $smallUnit = Unit::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'New Product',
                'small_unit_id' => $smallUnit->id,
                'sku' => 'TEST-SKU',
                'retail_price' => 100,
                'wholesale_price' => 90,
                'min_stock' => 10,
            ])
            ->call('create')
            ->assertHasFormErrors(['sku']);
    }

    // ===== SOFT DELETE TESTS =====

    public function test_soft_delete_product(): void
    {
        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product);

        $this->assertSoftDeleted($product);
    }
}
