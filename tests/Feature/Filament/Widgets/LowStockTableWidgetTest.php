<?php

use App\Filament\Widgets\Tables\LowStockTableWidget;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    // Create super admin role and user to bypass permission checks
    $role = Role::firstOrCreate(['name' => 'super_admin']);
    $this->user = User::factory()->create();
    $this->user->assignRole($role);
    $this->actingAs($this->user);
});

test('it lists low stock products', function () {
    $warehouse = Warehouse::factory()->create();

    // Product 1: Low Stock (Min 10, Current 5)
    $lowProduct = Product::factory()->create([
        'name' => 'Low Product',
        'min_stock' => 10,
    ]);
    StockMovement::create([
        'product_id' => $lowProduct->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 5,
        'type' => 'purchase',
        'reference_type' => 'manual',
        'reference_id' => 'init',
        'cost_at_time' => 10.00,
    ]);

    // Product 2: Good Stock (Min 10, Current 15)
    $goodProduct = Product::factory()->create([
        'name' => 'Good Product',
        'min_stock' => 10,
    ]);
    StockMovement::create([
        'product_id' => $goodProduct->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 15,
        'type' => 'purchase',
        'reference_type' => 'manual',
        'reference_id' => 'init',
        'cost_at_time' => 10.00,
    ]);

    // Product 3: Zero Stock (Min 10, Current 0)
    $zeroProduct = Product::factory()->create([
        'name' => 'Zero Product',
        'min_stock' => 10,
    ]);
    // No movements means 0 stock

    Livewire::test(LowStockTableWidget::class)
        ->assertCanSeeTableRecords([$lowProduct, $zeroProduct])
        ->assertCanNotSeeTableRecords([$goodProduct]);
});

test('it respects limit of 5', function () {
    $warehouse = Warehouse::factory()->create();

    // Create 6 low stock products
    $products = Product::factory()->count(6)->create(['min_stock' => 10]);

    foreach ($products as $product) {
        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'type' => 'purchase',
            'reference_type' => 'manual',
            'reference_id' => 'init',
            'cost_at_time' => 10.00,
        ]);
    }

    // Assert we see 5 records, but total count might be 6 if pagination/count logic works that way
    // We check that the 6th record is NOT visible if the limit works on the view

    // Actually, Filament's getAllTableRecordsCount might ignore limit.
    // Let's check the actual records returned.

    $component = Livewire::test(LowStockTableWidget::class);
    $records = $component->instance()->getTableRecords();

    expect($records)->toHaveCount(5);
});
