<?php

namespace App\Filament\Resources\WarehouseTransferResource\Pages;

use App\Filament\Resources\WarehouseTransferResource;
use App\Filament\Resources\WarehouseTransferResource\Widgets\WarehouseTransferStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListWarehouseTransfers extends ListRecords
{
    protected static string $resource = WarehouseTransferResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            WarehouseTransferStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\WarehouseTransfer::count()),
            'draft' => Tab::make('مسودة')
                ->icon('heroicon-m-pencil-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDoesntHave('stockMovements'))
                ->badgeColor('warning'),
            'posted' => Tab::make('مؤكدة')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('stockMovements'))
                ->badgeColor('success'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
