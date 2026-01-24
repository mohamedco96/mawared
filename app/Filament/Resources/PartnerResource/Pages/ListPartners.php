<?php

namespace App\Filament\Resources\PartnerResource\Pages;

use App\Filament\Resources\PartnerResource;
use App\Filament\Resources\PartnerResource\Widgets\PartnerStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPartners extends ListRecords
{
    protected static string $resource = PartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PartnerStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\Partner::count()),
            'customers' => Tab::make('العملاء')
                ->icon('heroicon-m-user-group')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'customer'))
                ->badge(fn () => \App\Models\Partner::where('type', 'customer')->count())
                ->badgeColor('success'),
            'suppliers' => Tab::make('الموردين')
                ->icon('heroicon-m-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'supplier'))
                ->badge(fn () => \App\Models\Partner::where('type', 'supplier')->count())
                ->badgeColor('warning'),
            'shareholders' => Tab::make('الشركاء')
                ->icon('heroicon-m-briefcase')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'shareholder'))
                ->badge(fn () => \App\Models\Partner::where('type', 'shareholder')->count())
                ->badgeColor('info'),
        ];
    }
}
