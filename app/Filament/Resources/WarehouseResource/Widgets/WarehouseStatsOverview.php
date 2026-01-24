<?php

namespace App\Filament\Resources\WarehouseResource\Widgets;

use App\Models\Warehouse;
use App\Models\StockMovement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class WarehouseStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.warehouse-resource.widgets.warehouse-stats-overview';

    protected function getStats(): array
    {
        $totalWarehouses = Warehouse::count();
        
        // Total stock quantity across all warehouses
        $totalStock = StockMovement::sum('quantity');

        // Warehouses that have stock > 0
        // We can use the relationship sum, but simpler to query StockMovement grouped by warehouse having sum > 0
        $warehousesWithStock = Warehouse::whereHas('stockMovements', function ($query) {
            $query->select(DB::raw('sum(quantity) as total_qty'))
                  ->havingRaw('total_qty > 0');
        })->count();
        
        // Or simpler: count distinct warehouse_ids in StockMovement where sum > 0? 
        // StockMovement tracks movements, not current stock snapshot per se, but sum(quantity) IS the current stock.
        // But we need to group by warehouse.
        // Actually, StockMovement table stores +ve and -ve movements. Sum is balance.
        
        $warehousesWithStockCount = DB::table('stock_movements')
            ->select('warehouse_id')
            ->groupBy('warehouse_id')
            ->havingRaw('SUM(quantity) > 0')
            ->get()
            ->count();

        return [
            Stat::make('إجمالي المخازن', $totalWarehouses)
                ->description('عدد المخازن المعرفة')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),

            Stat::make('مخازن بها أرصدة', $warehousesWithStockCount)
                ->description('مخازن تحتوي على منتجات')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('success'),

            Stat::make('إجمالي المخزون', number_format($totalStock))
                ->description('إجمالي عدد الوحدات في جميع المخازن')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),
        ];
    }
}
