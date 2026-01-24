<?php

namespace App\Filament\Resources\QuotationResource\Widgets;

use App\Models\Quotation;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class QuotationStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.quotation-resource.widgets.quotation-stats-overview';

    protected function getStats(): array
    {
        // Accepted Quotations Value
        $acceptedValue = Quotation::where('status', 'accepted')->sum('total');

        // Pending Quotations Value (Draft + Sent)
        $pendingValue = Quotation::whereIn('status', ['draft', 'sent'])->sum('total');

        // Pending Count
        $pendingCount = Quotation::whereIn('status', ['draft', 'sent'])->count();

        // Expired Count
        $expiredCount = Quotation::where('status', 'expired')->count();

        return [
            Stat::make('قيمة العروض المقبولة', number_format($acceptedValue, 2))
                ->description('إجمالي قيمة العروض التي تم قبولها')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('قيمة العروض المعلقة', number_format($pendingValue, 2))
                ->description('مسودة أو مرسلة')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('عروض بانتظار الرد', $pendingCount)
                ->description('عدد العروض السارية')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('info'),

            Stat::make('عروض منتهية', $expiredCount)
                ->description('انتهت صلاحيتها')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
