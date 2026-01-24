<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use App\Filament\Resources\PurchaseReturnResource\Widgets\PurchaseReturnStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseReturns extends ListRecords
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PurchaseReturnStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\PurchaseReturn::count()),
            'draft' => Tab::make('مسودة')
                ->icon('heroicon-m-document')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => \App\Models\PurchaseReturn::where('status', 'draft')->count())
                ->badgeColor('warning'),
            'posted' => Tab::make('مؤكدة')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted'))
                ->badge(fn () => \App\Models\PurchaseReturn::where('status', 'posted')->count())
                ->badgeColor('success'),
        ];
    }
}
