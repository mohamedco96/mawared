<?php

namespace App\Filament\Resources\ActivityLogResource\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Spatie\Activitylog\Models\Activity;

class ActivityLogStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.activity-log-resource.widgets.activity-log-stats-overview';

    protected function getStats(): array
    {
        $todayCount = Activity::whereDate('created_at', today())->count();
        $totalCount = Activity::count();
        $userCount = Activity::where('subject_type', 'user')->count(); // Example metric

        return [
            Stat::make('نشاطات اليوم', $todayCount)
                ->description('عدد العمليات المسجلة اليوم')
                ->descriptionIcon('heroicon-m-clock')
                ->color('primary'),
            Stat::make('إجمالي السجلات', $totalCount)
                ->description('إجمالي سجل النشاطات')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray'),
            Stat::make('تعديلات المستخدمين', $userCount)
                ->description('تغييرات على المستخدمين')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}
