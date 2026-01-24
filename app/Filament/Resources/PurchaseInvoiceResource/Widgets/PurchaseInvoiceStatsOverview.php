<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Widgets;

use App\Models\PurchaseInvoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PurchaseInvoiceStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.purchase-invoice-resource.widgets.purchase-invoice-stats-overview';

    protected function getStats(): array
    {
        $totalInvoices = PurchaseInvoice::count();
        $draftInvoices = PurchaseInvoice::where('status', 'draft')->count();
        $postedInvoices = PurchaseInvoice::where('status', 'posted')->count();
        $totalDebt = PurchaseInvoice::where('status', 'posted')
            ->where('remaining_amount', '>', 0)
            ->sum('remaining_amount');

        return [
            Stat::make('إجمالي الفواتير', $totalInvoices)
                ->description('عدد فواتير الشراء الكلي')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('إجمالي الديون', number_format($totalDebt, 2))
                ->description('المبالغ المتبقية للموردين')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('مسودة', $draftInvoices)
                ->description('فواتير قيد الانتظار')
                ->descriptionIcon('heroicon-m-document')
                ->color('warning'),

            Stat::make('مؤكدة', $postedInvoices)
                ->description('فواتير تم ترحيلها')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}
