<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Resources\WarehouseResource;
use App\Filament\Resources\WarehouseResource\Widgets\WarehouseStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWarehouses extends ListRecords
{
    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            WarehouseStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\Warehouse::count()),
            'with_stock' => Tab::make('بها أرصدة')
                ->icon('heroicon-m-archive-box')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('stockMovements', function ($q) {
                    $q->selectRaw('warehouse_id, sum(quantity) as total_qty')
                      ->groupBy('warehouse_id')
                      ->havingRaw('sum(quantity) > 0');
                }))
                ->badgeColor('success'),
            'empty' => Tab::make('فارغة')
                ->icon('heroicon-m-archive-box-x-mark')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('stockMovements')
                    ->orWhereHas('stockMovements', function ($q) {
                        $q->selectRaw('warehouse_id, sum(quantity) as total_qty')
                          ->groupBy('warehouse_id')
                          ->havingRaw('sum(quantity) = 0');
                    }))
                ->badgeColor('gray'),
        ];
    }
}
