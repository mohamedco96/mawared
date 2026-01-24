<?php

namespace App\Filament\Resources\PartnerResource\Widgets;

use App\Models\Partner;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PartnerStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.partner-resource.widgets.partner-stats-overview';

    protected function getStats(): array
    {
        $totalPartners = Partner::count();
        $customersCount = Partner::where('type', 'customer')->count();
        $suppliersCount = Partner::where('type', 'supplier')->count();
        $shareholdersCount = Partner::where('type', 'shareholder')->count();

        // Calculate total receivables (from customers) and payables (to suppliers)
        // Assuming current_balance > 0 means they owe us (receivable) or we owe them?
        // Usually: Customer +ve balance = Receivable (Asset)
        // Supplier +ve balance = Payable (Liability)
        // Need to check business logic. Assuming standard:
        // If Customer balance is positive, they owe us.
        // If Supplier balance is positive, we owe them.

        $totalReceivables = Partner::where('type', 'customer')->where('current_balance', '>', 0)->sum('current_balance');
        $totalPayables = abs(Partner::where('type', 'supplier')->where('current_balance', '<', 0)->sum('current_balance'));

        return [
            Stat::make('إجمالي الشركاء', $totalPartners)
                ->description('العملاء والموردين والمساهمين')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('إجمالي العملاء', $customersCount)
                ->description('عدد العملاء المسجلين')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('إجمالي الموردين', $suppliersCount)
                ->description('عدد الموردين المسجلين')
                ->descriptionIcon('heroicon-m-truck')
                ->color('warning'),

            Stat::make('مديونيات العملاء', number_format($totalReceivables, 2))
                ->description('إجمالي المبالغ المستحقة لنا')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('مستحقات الموردين', number_format($totalPayables, 2))
                ->description('إجمالي المبالغ المستحقة علينا')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
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
