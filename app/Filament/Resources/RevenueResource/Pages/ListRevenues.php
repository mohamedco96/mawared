<?php

namespace App\Filament\Resources\RevenueResource\Pages;

use App\Filament\Resources\RevenueResource;
use App\Filament\Resources\RevenueResource\Widgets\RevenueStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRevenues extends ListRecords
{
    protected static string $resource = RevenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RevenueStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\Revenue::count()),
            'today' => Tab::make('اليوم')
                ->icon('heroicon-m-calendar-days')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('revenue_date', today()))
                ->badge(fn () => \App\Models\Revenue::whereDate('revenue_date', today())->count())
                ->badgeColor('info'),
            'month' => Tab::make('هذا الشهر')
                ->icon('heroicon-m-calendar')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereMonth('revenue_date', now()->month)
                          ->whereYear('revenue_date', now()->year)
                )
                ->badge(fn () => \App\Models\Revenue::whereMonth('revenue_date', now()->month)
                        ->whereYear('revenue_date', now()->year)->count())
                ->badgeColor('primary'),
        ];
    }
}
