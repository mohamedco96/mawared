<?php

namespace App\Filament\Widgets;

use App\Models\StockMovement;
use App\Models\TreasuryTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DailyOperationsStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.daily-operations-stats-overview';

    protected function getStats(): array
    {
        // 1. Total Collections Today (Sales Flow)
        $todayCollections = TreasuryTransaction::where('type', 'collection')
            ->whereDate('created_at', today())
            ->sum('amount');

        // 2. Total Payments/Expenses Today
        $todayPayments = TreasuryTransaction::whereIn('type', ['payment', 'expense'])
            ->whereDate('created_at', today())
            ->sum('amount');

        // 3. Net Cashflow Today
        $netCashflow = $todayCollections - $todayPayments;

        // 4. Stock Movements Today
        $stockMovementsCount = StockMovement::whereDate('created_at', today())->count();

        return [
            Stat::make('تحصيلات اليوم', number_format($todayCollections, 2))
                ->description('إجمالي المقبوضات اليومية')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('مدفوعات اليوم', number_format($todayPayments, 2))
                ->description('إجمالي المصروفات والمدفوعات')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('صافي التدفق اليومي', number_format($netCashflow, 2))
                ->description('الفرق بين التحصيل والدفع')
                ->descriptionIcon('heroicon-m-scale')
                ->color($netCashflow >= 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('حركات المخزون', $stockMovementsCount)
                ->description('عدد الحركات المسجلة اليوم')
                ->descriptionIcon('heroicon-m-cube')
                ->color('info'),
        ];
    }
}
