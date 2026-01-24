<?php

namespace App\Filament\Resources\TreasuryResource\Widgets;

use App\Models\Treasury;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TreasuryStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.treasury-resource.widgets.treasury-stats-overview';

    protected function getStats(): array
    {
        $totalBalance = DB::table('treasury_transactions')->sum('amount');
        
        $cashTreasuryIds = Treasury::where('type', 'cash')->pluck('id');
        $cashBalance = DB::table('treasury_transactions')
            ->whereIn('treasury_id', $cashTreasuryIds)
            ->sum('amount');

        $bankTreasuryIds = Treasury::where('type', 'bank')->pluck('id');
        $bankBalance = DB::table('treasury_transactions')
            ->whereIn('treasury_id', $bankTreasuryIds)
            ->sum('amount');

        return [
            Stat::make('الرصيد الإجمالي', number_format($totalBalance, 2))
                ->description('إجمالي الأرصدة في جميع الخزائن')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('رصيد النقدية', number_format($cashBalance, 2))
                ->description('إجمالي النقدية في الخزائن')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('رصيد البنوك', number_format($bankBalance, 2))
                ->description('إجمالي الأرصدة في البنوك')
                ->descriptionIcon('heroicon-m-building-library')
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
