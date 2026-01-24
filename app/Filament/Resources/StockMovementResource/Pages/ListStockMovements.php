<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Resources\StockMovementResource;
use App\Filament\Resources\StockMovementResource\Widgets\StockMovementStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            StockMovementStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\StockMovement::count()),
            'sales' => Tab::make('مبيعات')
                ->icon('heroicon-m-shopping-cart')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'sale'))
                ->badgeColor('success'),
            'purchases' => Tab::make('مشتريات')
                ->icon('heroicon-m-shopping-bag')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'purchase'))
                ->badgeColor('info'),
            'transfers' => Tab::make('نقل')
                ->icon('heroicon-m-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'transfer'))
                ->badgeColor('warning'),
            'adjustments' => Tab::make('تسويات')
                ->icon('heroicon-m-adjustments-horizontal')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', ['adjustment_in', 'adjustment_out']))
                ->badgeColor('gray'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
