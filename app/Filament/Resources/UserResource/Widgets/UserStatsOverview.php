<?php

namespace App\Filament\Resources\UserResource\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.user-resource.widgets.user-stats-overview';

    protected function getStats(): array
    {
        $totalUsers = User::count();
        $dailyUsers = User::where('salary_type', 'daily')->count();
        $monthlyUsers = User::where('salary_type', 'monthly')->count();
        $totalSalary = User::sum('salary_amount');

        return [
            Stat::make('إجمالي المستخدمين', $totalUsers)
                ->description('عدد المستخدمين المسجلين')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('رواتب يومية', $dailyUsers)
                ->description('موظفين بنظام يومي')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('info'),

            Stat::make('رواتب شهرية', $monthlyUsers)
                ->description('موظفين بنظام شهري')
                ->descriptionIcon('heroicon-m-calendar-date-range')
                ->color('success'),

            Stat::make('إجمالي الرواتب', number_format($totalSalary, 2))
                ->description('مجموع الرواتب المسجلة')
                ->descriptionIcon('heroicon-m-banknotes')
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
