<?php

namespace App\Filament\Resources\SalesReturnResource\Pages;

use App\Filament\Resources\SalesReturnResource;
use App\Filament\Resources\SalesReturnResource\Widgets\SalesReturnStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSalesReturns extends ListRecords
{
    protected static string $resource = SalesReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            SalesReturnStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\SalesReturn::count()),
            'draft' => Tab::make('مسودة')
                ->icon('heroicon-m-document')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => \App\Models\SalesReturn::where('status', 'draft')->count())
                ->badgeColor('warning'),
            'posted' => Tab::make('مؤكدة')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted'))
                ->badge(fn () => \App\Models\SalesReturn::where('status', 'posted')->count())
                ->badgeColor('success'),
        ];
    }
}
