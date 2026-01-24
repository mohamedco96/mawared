<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use App\Filament\Resources\StockAdjustmentResource\Widgets\StockAdjustmentStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListStockAdjustments extends ListRecords
{
    protected static string $resource = StockAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StockAdjustmentStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\StockAdjustment::count()),
            'draft' => Tab::make('مسودة')
                ->icon('heroicon-m-pencil-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => \App\Models\StockAdjustment::where('status', 'draft')->count())
                ->badgeColor('warning'),
            'posted' => Tab::make('مؤكدة')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted'))
                ->badge(fn () => \App\Models\StockAdjustment::where('status', 'posted')->count())
                ->badgeColor('success'),
            'addition' => Tab::make('إضافات')
                ->icon('heroicon-m-plus-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', ['addition', 'opening', 'other']))
                ->badge(fn () => \App\Models\StockAdjustment::whereIn('type', ['addition', 'opening', 'other'])->count())
                ->badgeColor('info'),
            'subtraction' => Tab::make('خصومات')
                ->icon('heroicon-m-minus-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', ['subtraction', 'damage', 'gift']))
                ->badge(fn () => \App\Models\StockAdjustment::whereIn('type', ['subtraction', 'damage', 'gift'])->count())
                ->badgeColor('danger'),
        ];
    }
}
