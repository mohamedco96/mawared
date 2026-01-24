<?php

namespace App\Filament\Resources\InstallmentResource\Widgets;

use App\Models\Installment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class InstallmentStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.installment-resource.widgets.installment-stats-overview';

    protected function getStats(): array
    {
        // Total Outstanding Amount (Remaining Amount on Unpaid/Partial Installments)
        $totalOutstanding = Installment::where('status', '!=', 'paid')
            ->sum(DB::raw('amount - paid_amount'));

        // Overdue Amount
        $overdueAmount = Installment::where(function ($query) {
            $query->where('status', 'overdue')
                ->orWhere(function ($q) {
                    $q->where('status', '!=', 'paid')
                        ->where('due_date', '<', now());
                });
        })->sum(DB::raw('amount - paid_amount'));

        // Collected Today
        $collectedToday = Installment::whereDate('paid_at', today())->sum('paid_amount');

        // Pending Installments Count
        $pendingCount = Installment::where('status', 'pending')->count();

        return [
            Stat::make('إجمالي المبالغ المستحقة', number_format($totalOutstanding, 2))
                ->description('إجمالي الأقساط المتبقية')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('أقساط متأخرة', number_format($overdueAmount, 2))
                ->description('مبالغ تجاوزت تاريخ الاستحقاق')
                ->descriptionIcon('heroicon-m-clock')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('تم تحصيله اليوم', number_format($collectedToday, 2))
                ->description('تحصيلات الأقساط اليومية')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('أقساط قيد الانتظار', $pendingCount)
                ->description('عدد الأقساط القادمة')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
