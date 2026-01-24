<?php

namespace App\Filament\Resources\TreasuryResource\Pages;

use App\Filament\Resources\TreasuryResource;
use App\Filament\Resources\TreasuryResource\Widgets\TreasuryStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTreasuries extends ListRecords
{
    protected static string $resource = TreasuryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TreasuryStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\Treasury::count()),
            'cash' => Tab::make('نقدية')
                ->icon('heroicon-m-currency-dollar')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'cash'))
                ->badge(fn () => \App\Models\Treasury::where('type', 'cash')->count())
                ->badgeColor('success'),
            'bank' => Tab::make('بنوك')
                ->icon('heroicon-m-building-library')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'bank'))
                ->badge(fn () => \App\Models\Treasury::where('type', 'bank')->count())
                ->badgeColor('info'),
        ];
    }
}
