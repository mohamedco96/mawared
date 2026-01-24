<?php

namespace App\Filament\Resources\EquityPeriodResource\Pages;

use App\Filament\Resources\EquityPeriodResource;
use App\Filament\Resources\EquityPeriodResource\Widgets\EquityPeriodStatsOverview;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEquityPeriods extends ListRecords
{
    protected static string $resource = EquityPeriodResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            EquityPeriodStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\EquityPeriod::count()),
            'open' => Tab::make('مفتوحة')
                ->icon('heroicon-m-play-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'open'))
                ->badgeColor('success'),
            'closed' => Tab::make('مغلقة')
                ->icon('heroicon-m-stop-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'closed'))
                ->badgeColor('gray'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // We don't allow manual creation - periods are created automatically
        ];
    }
}
