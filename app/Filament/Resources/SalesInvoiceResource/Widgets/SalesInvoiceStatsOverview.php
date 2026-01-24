<?php

namespace App\Filament\Resources\SalesInvoiceResource\Widgets;

use App\Models\SalesInvoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SalesInvoiceStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.sales-invoice-resource.widgets.sales-stats-overview';

    protected function getStats(): array
    {
        // Total Sales (Posted)
        $totalSales = SalesInvoice::where('status', 'posted')->sum('total');

        // Due Amount (Remaining on Posted Invoices)
        $dueAmount = SalesInvoice::where('status', 'posted')->sum('remaining_amount');

        // Today's Sales
        $todaySales = SalesInvoice::where('status', 'posted')
            ->whereDate('created_at', today())
            ->sum('total');

        // Draft Invoices Count
        $draftCount = SalesInvoice::where('status', 'draft')->count();

        return [
            Stat::make('إجمالي المبيعات', number_format($totalSales, 2))
                ->description('إجمالي الفواتير المؤكدة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('المستحقات (الآجل)', number_format($dueAmount, 2))
                ->description('المبالغ المتبقية غير المدفوعة')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('مبيعات اليوم', number_format($todaySales, 2))
                ->description('إجمالي مبيعات اليوم')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('info')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('فواتير مسودة', $draftCount)
                ->description('بانتظار التأكيد')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),
        ];
    }
}
