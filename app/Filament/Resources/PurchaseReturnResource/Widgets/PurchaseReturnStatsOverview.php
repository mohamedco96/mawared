<?php

namespace App\Filament\Resources\PurchaseReturnResource\Widgets;

use App\Models\PurchaseReturn;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PurchaseReturnStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.purchase-return-resource.widgets.purchase-return-stats-overview';

    protected function getStats(): array
    {
        $totalReturns = PurchaseReturn::count();
        $draftReturns = PurchaseReturn::where('status', 'draft')->count();
        $postedReturns = PurchaseReturn::where('status', 'posted')->count();
        $totalValue = PurchaseReturn::where('status', 'posted')->sum('total');

        return [
            Stat::make('إجمالي المرتجعات', $totalReturns)
                ->description('عدد مرتجعات المشتريات الكلي')
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
