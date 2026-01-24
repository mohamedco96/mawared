<?php

namespace App\Filament\Resources\EquityPeriodResource\Widgets;

use App\Models\EquityPeriod;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EquityPeriodStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.equity-period-resource.widgets.equity-period-stats-overview';

    protected function getStats(): array
    {
        $totalPeriods = EquityPeriod::count();
        $openPeriods = EquityPeriod::where('status', 'open')->count();
        $closedPeriods = EquityPeriod::where('status', 'closed')->count();
        
        $totalNetProfit = EquityPeriod::where('status', 'closed')->sum('net_profit');

        return [
            Stat::make('إجمالي الفترات', $totalPeriods)
                ->description('عدد فترات رأس المال')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('فترات مفتوحة', $openPeriods)
                ->description('فترات قيد التشغيل')
                ->descriptionIcon('heroicon-m-play-circle')
                ->color('success'),

            Stat::make('فترات مغلقة', $closedPeriods)
                ->description('فترات منتهية')
                ->descriptionIcon('heroicon-m-stop-circle')
                ->color('gray'),

            Stat::make('صافي الأرباح (المغلقة)', number_format($totalNetProfit, 2))
                ->description('أرباح الفترات المغلقة')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
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
