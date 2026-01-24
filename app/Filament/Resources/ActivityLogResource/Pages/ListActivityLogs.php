<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\ActivityLogResource\Widgets\ActivityLogStatsOverview;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ActivityLogStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \Spatie\Activitylog\Models\Activity::count()),
            'today' => Tab::make('اليوم')
                ->icon('heroicon-m-calendar-days')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('created_at', today()))
                ->badge(fn () => \Spatie\Activitylog\Models\Activity::whereDate('created_at', today())->count())
                ->badgeColor('success'),
            'created' => Tab::make('إضافات')
                ->icon('heroicon-m-plus-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('event', 'created'))
                ->badgeColor('success'),
            'updated' => Tab::make('تعديلات')
                ->icon('heroicon-m-pencil-square')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('event', 'updated'))
                ->badgeColor('warning'),
            'deleted' => Tab::make('حذوفات')
                ->icon('heroicon-m-trash')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('event', 'deleted'))
                ->badgeColor('danger'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // No create action for read-only resource
        ];
    }
}
