<?php

namespace App\Filament\Resources\SalesReturnResource\Widgets;

use App\Models\SalesReturn;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SalesReturnStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.sales-return-resource.widgets.sales-return-stats-overview';

    protected function getStats(): array
    {
        $totalReturns = SalesReturn::count();
        $draftReturns = SalesReturn::where('status', 'draft')->count();
        $postedReturns = SalesReturn::where('status', 'posted')->count();
        $totalValue = SalesReturn::where('status', 'posted')->sum('total');

        return [
            Stat::make('إجمالي المرتجعات', $totalReturns)
                ->description('عدد المرتجعات الكلي')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('قيمة المرتجعات المؤكدة', number_format($totalValue, 2))
                ->description('إجمالي قيمة المرتجعات المؤكدة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('مسودة', $draftReturns)
                ->description('مرتجعات قيد الانتظار')
                ->descriptionIcon('heroicon-m-document')
                ->color('warning'),

            Stat::make('مؤكدة', $postedReturns)
                ->description('مرتجعات تم ترحيلها')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
