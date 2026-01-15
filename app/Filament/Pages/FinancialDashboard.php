<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FinancialDashboard extends Page
{
    protected static string $view = 'filament-panels::pages.dashboard';
    protected static ?string $navigationLabel = 'الوضع المالي';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationGroup = 'أخرى'; // Reports group

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'الوضع المالي';

    protected static ?string $slug = 'financial-dashboard';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        
        if (!$user) {
            return false;
        }

        // Super admin always has access
        if ($user->hasRole('super_admin')) {
            return true;
        }

        // Check for specific permission
        return $user->can('view_financial_dashboard') ?? false;
    }

    public function getHeading(): string
    {
        return 'الوضع المالي';
    }

    /**
     * Get the widgets that should be displayed on this dashboard.
     * Only financial widgets should appear here.
     */
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\FinancialOverviewWidget::class,
            \App\Filament\Widgets\Charts\CashFlowChartWidget::class,
            \App\Filament\Widgets\Tables\TopDebtorsTableWidget::class,
            \App\Filament\Widgets\Tables\TopCreditorsTableWidget::class,
            \App\Filament\Widgets\InventoryValueWidget::class,
        ];
    }

    public function getVisibleWidgets(): array
    {
        return $this->getWidgets();
    }

    /**
     * @return int | array<string, int | null>
     */
    public function getColumns(): int | array
    {
        return 2;
    }
}
