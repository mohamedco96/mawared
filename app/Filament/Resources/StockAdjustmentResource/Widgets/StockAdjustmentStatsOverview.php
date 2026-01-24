<?php

namespace App\Filament\Resources\StockAdjustmentResource\Widgets;

use App\Models\StockAdjustment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StockAdjustmentStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.stock-adjustment-resource.widgets.stock-adjustment-stats-overview';

    protected function getStats(): array
    {
        $totalAdjustments = StockAdjustment::count();
        
        $additions = StockAdjustment::whereIn('type', ['addition', 'opening', 'other'])->count();
        $subtractions = StockAdjustment::whereIn('type', ['subtraction', 'damage', 'gift'])->count();
        
        $posted = StockAdjustment::where('status', 'posted')->count();

        return [
            Stat::make('إجمالي التسويات', $totalAdjustments)
                ->description('عدد عمليات التسوية')
                ->descriptionIcon('heroicon-m-adjustments-horizontal')
                ->color('primary'),

            Stat::make('إضافات', $additions)
                ->description('زيادة في المخزون')
                ->descriptionIcon('heroicon-m-plus-circle')
                ->color('success'),

            Stat::make('خصومات', $subtractions)
                ->description('نقص في المخزون')
                ->descriptionIcon('heroicon-m-minus-circle')
                ->color('danger'),
                
            Stat::make('مؤكدة', $posted)
                ->description('تسويات تم ترحيلها')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
        ];
    }
}
