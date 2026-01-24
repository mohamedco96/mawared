<?php

namespace App\Filament\Resources\RevenueResource\Widgets;

use App\Models\Revenue;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RevenueStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.revenue-resource.widgets.revenue-stats-overview';

    protected function getStats(): array
    {
        $totalRevenue = Revenue::sum('amount');
        $todayRevenue = Revenue::whereDate('revenue_date', today())->sum('amount');
        $monthRevenue = Revenue::whereMonth('revenue_date', now()->month)
            ->whereYear('revenue_date', now()->year)
            ->sum('amount');

        return [
            Stat::make('إجمالي الإيرادات', number_format($totalRevenue, 2))
                ->description('إجمالي الإيرادات المسجلة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('إيرادات اليوم', number_format($todayRevenue, 2))
                ->description('الإيرادات المسجلة اليوم')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('إيرادات الشهر', number_format($monthRevenue, 2))
                ->description('الإيرادات المسجلة هذا الشهر')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary')
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
