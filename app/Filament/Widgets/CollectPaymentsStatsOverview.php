<?php

namespace App\Filament\Widgets;

use App\Models\SalesInvoice;
use App\Models\TreasuryTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CollectPaymentsStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.widgets.collect-payments-stats-overview';

    protected function getStats(): array
    {
        // Total to be collected (Remaining Amount on Posted Invoices)
        $totalToCollect = SalesInvoice::where('status', 'posted')
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');

        // Collected Today (Treasury Transactions of type 'collection')
        $collectedToday = TreasuryTransaction::where('type', 'collection')
            ->whereDate('created_at', today())
            ->sum('amount');

        // Overdue Invoices (Older than 30 days)
        $overdueQuery = SalesInvoice::where('status', 'posted')
            ->where('remaining_amount', '>', 0)
            ->where('created_at', '<', now()->subDays(30));
        
        $overdueCount = $overdueQuery->count();
        $overdueAmount = $overdueQuery->sum('remaining_amount');

        return [
            Stat::make('المبلغ المتبقي للتحصيل', number_format($totalToCollect, 2))
                ->description('إجمالي المبالغ المستحقة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('تم تحصيله اليوم', number_format($collectedToday, 2))
                ->description('إجمالي التحصيلات اليومية')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('فواتير متأخرة', number_format($overdueAmount, 2))
                ->description($overdueCount . ' فاتورة متأخرة (> 30 يوم)')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
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
