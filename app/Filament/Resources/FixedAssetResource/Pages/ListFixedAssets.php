<?php

namespace App\Filament\Resources\FixedAssetResource\Pages;

use App\Filament\Resources\FixedAssetResource;
use App\Filament\Resources\FixedAssetResource\Widgets\FixedAssetStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListFixedAssets extends ListRecords
{
    protected static string $resource = FixedAssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FixedAssetStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\FixedAsset::count()),
            'cash' => Tab::make('نقدي')
                ->icon('heroicon-m-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('funding_method', 'cash'))
                ->badge(fn () => \App\Models\FixedAsset::where('funding_method', 'cash')->count())
                ->badgeColor('success'),
            'payable' => Tab::make('آجل')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('funding_method', 'payable'))
                ->badge(fn () => \App\Models\FixedAsset::where('funding_method', 'payable')->count())
                ->badgeColor('warning'),
            'equity' => Tab::make('رأسمالي')
                ->icon('heroicon-m-user-group')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('funding_method', 'equity'))
                ->badge(fn () => \App\Models\FixedAsset::where('funding_method', 'equity')->count())
                ->badgeColor('info'),
        ];
    }
}
