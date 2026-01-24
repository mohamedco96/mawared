<?php

namespace App\Filament\Resources\StockMovementResource\Widgets;

use App\Models\StockMovement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockMovementStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.stock-movement-resource.widgets.stock-movement-stats-overview';

    protected function getStats(): array
    {
        $totalMovements = StockMovement::count();
        $sales = StockMovement::where('type', 'sale')->count();
        $purchases = StockMovement::where('type', 'purchase')->count();
        $transfers = StockMovement::where('type', 'transfer')->count();
        $adjustments = StockMovement::whereIn('type', ['adjustment_in', 'adjustment_out'])->count();

        return [
            Stat::make('إجمالي الحركات', $totalMovements)
                ->description('عدد حركات المخزون')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('primary'),

            Stat::make('مبيعات', $sales)
                ->description('حركات بيع')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),

            Stat::make('مشتريات', $purchases)
                ->description('حركات شراء')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),
                
            Stat::make('نقل مخزون', $transfers)
                ->description('حركات نقل')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning'),

            Stat::make('تسويات', $adjustments)
                ->description('تسويات جردية')
                ->descriptionIcon('heroicon-m-adjustments-horizontal')
                ->color('gray'),
        ];
    }
}
