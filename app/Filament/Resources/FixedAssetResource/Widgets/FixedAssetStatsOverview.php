<?php

namespace App\Filament\Resources\FixedAssetResource\Widgets;

use App\Models\FixedAsset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FixedAssetStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.fixed-asset-resource.widgets.fixed-asset-stats-overview';

    protected function getStats(): array
    {
        $totalAssets = FixedAsset::count();
        $totalValue = FixedAsset::sum('purchase_amount');
        
        // Count assets that are fully depreciated or close to it?
        // Without complex calculation, maybe just assets older than useful_life_years?
        // Let's just stick to simple stats for now: Total Value, Count, and Funding methods split.

        $cashFunded = FixedAsset::where('funding_method', 'cash')->sum('purchase_amount');
        $payableFunded = FixedAsset::where('funding_method', 'payable')->sum('purchase_amount');
        $equityFunded = FixedAsset::where('funding_method', 'equity')->sum('purchase_amount');

        return [
            Stat::make('إجمالي الأصول', $totalAssets)
                ->description('عدد الأصول المسجلة')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('القيمة الإجمالية', number_format($totalValue, 2))
                ->description('قيمة شراء جميع الأصول')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('تمويل نقدي', number_format($cashFunded, 2))
                ->description('أصول ممولة من الخزينة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),
                
            Stat::make('تمويل آجل', number_format($payableFunded, 2))
                ->description('أصول ممولة بالدين')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
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
