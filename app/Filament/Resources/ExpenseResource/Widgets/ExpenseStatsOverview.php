<?php

namespace App\Filament\Resources\ExpenseResource\Widgets;

use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ExpenseStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.expense-resource.widgets.expense-stats-overview';

    protected function getStats(): array
    {
        $totalExpenses = Expense::sum('amount');
        $todayExpenses = Expense::whereDate('expense_date', today())->sum('amount');
        $monthExpenses = Expense::whereMonth('expense_date', now()->month)
            ->whereYear('expense_date', now()->year)
            ->sum('amount');

        return [
            Stat::make('إجمالي المصروفات', number_format($totalExpenses, 2))
                ->description('إجمالي المصروفات المسجلة')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('مصروفات اليوم', number_format($todayExpenses, 2))
                ->description('المصروفات المسجلة اليوم')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('مصروفات الشهر', number_format($monthExpenses, 2))
                ->description('المصروفات المسجلة هذا الشهر')
                ->descriptionIcon('heroicon-m-calendar')
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
