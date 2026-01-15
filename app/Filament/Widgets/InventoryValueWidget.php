<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryValueWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_InventoryValueWidget') ?? false;
    }

    protected function getStats(): array
    {
        return Cache::remember('dashboard.inventory_value', 300, function () {
            // Inventory Value: Sum of (current stock × avg_cost) for all products
            $inventoryValue = DB::table('products')
                ->selectRaw('SUM(
                    (SELECT COALESCE(SUM(quantity), 0)
                     FROM stock_movements
                     WHERE stock_movements.product_id = products.id
                     AND stock_movements.deleted_at IS NULL)
                    * products.avg_cost
                ) as total_value')
                ->whereNull('deleted_at')
                ->value('total_value') ?? 0;

            return [
                Stat::make('قيمة المخزون', number_format($inventoryValue, 2))
                    ->description('إجمالي قيمة المخزون الحالي')
                    ->icon('heroicon-o-cube')
                    ->color('info'),
            ];
        });
    }
}
