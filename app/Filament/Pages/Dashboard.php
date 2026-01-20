<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -100;

    protected static string $view = 'filament.pages.dashboard';

    public function getHeading(): string
    {
        return '';
    }

    /**
     * Get the widgets that should be displayed on the main dashboard.
     * Only operational widgets (no financial data) should appear here.
     */
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\OperationalStatsWidget::class,
            \App\Filament\Widgets\Tables\LowStockTableWidget::class,
            \App\Filament\Widgets\Tables\BestSellersWidget::class,
            \App\Filament\Widgets\LatestActivitiesWidget::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
