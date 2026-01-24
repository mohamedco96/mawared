<?php

namespace App\Filament\Resources\UnitResource\Widgets;

use App\Models\Unit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UnitStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.unit-resource.widgets.unit-stats-overview';

    protected function getStats(): array
    {
        $totalUnits = Unit::count();

        return [
            Stat::make('إجمالي الوحدات', $totalUnits)
                ->description('عدد وحدات القياس المسجلة')
                ->descriptionIcon('heroicon-m-scale')
                ->color('primary'),
        ];
    }
}
