<?php

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RemainingResourcesSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'super_admin']);
        $user = User::factory()->create();
        $user->assignRole($role);

        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        $this->actingAs($user);
    }

    /**
     * Data provider for resources to test.
     * Format: [Resource Class, Page Class, Page Name (for assertion if needed)]
     */
    public static function resourceProvider(): array
    {
        return [
            // Master Data
            ['UnitResource', \App\Filament\Resources\UnitResource\Pages\ListUnits::class],
            ['WarehouseResource', \App\Filament\Resources\WarehouseResource\Pages\ListWarehouses::class],
            ['ProductCategoryResource', \App\Filament\Resources\ProductCategoryResource\Pages\ListProductCategories::class],
            
            // Transactions & Operations
            ['QuotationResource', \App\Filament\Resources\QuotationResource\Pages\ListQuotations::class],
            ['SalesReturnResource', \App\Filament\Resources\SalesReturnResource\Pages\ListSalesReturns::class],
            ['PurchaseReturnResource', \App\Filament\Resources\PurchaseReturnResource\Pages\ListPurchaseReturns::class],
            ['StockAdjustmentResource', \App\Filament\Resources\StockAdjustmentResource\Pages\ListStockAdjustments::class],
            ['WarehouseTransferResource', \App\Filament\Resources\WarehouseTransferResource\Pages\ListWarehouseTransfers::class],
            
            // Financials
            ['ExpenseResource', \App\Filament\Resources\ExpenseResource\Pages\ListExpenses::class],
            ['RevenueResource', \App\Filament\Resources\RevenueResource\Pages\ListRevenues::class],
            ['FixedAssetResource', \App\Filament\Resources\FixedAssetResource\Pages\ListFixedAssets::class],
            ['InstallmentResource', \App\Filament\Resources\InstallmentResource\Pages\ListInstallments::class],
            ['EquityPeriodResource', \App\Filament\Resources\EquityPeriodResource\Pages\ListEquityPeriods::class],
            
            // Reporting / Logs
            ['StockMovementResource', \App\Filament\Resources\StockMovementResource\Pages\ListStockMovements::class],
            ['TreasuryTransactionResource', \App\Filament\Resources\TreasuryTransactionResource\Pages\ListTreasuryTransactions::class],
            ['ActivityLogResource', \App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs::class],
        ];
    }

    /**
     * @dataProvider resourceProvider
     */
    public function test_resource_list_page_renders($resourceName, $pageClass): void
    {
        // Simple check to ensure the component mounts without error
        Livewire::test($pageClass)
            ->assertStatus(200);
    }
}
