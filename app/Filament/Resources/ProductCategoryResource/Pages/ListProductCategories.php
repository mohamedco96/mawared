<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use App\Filament\Resources\ProductCategoryResource\Widgets\ProductCategoryStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListProductCategories extends ListRecords
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ProductCategoryStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\ProductCategory::count()),
            'main' => Tab::make('رئيسية')
                ->icon('heroicon-m-folder')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('parent_id'))
                ->badgeColor('info'),
            'sub' => Tab::make('فرعية')
                ->icon('heroicon-m-folder-open')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('parent_id'))
                ->badgeColor('warning'),
            'active' => Tab::make('نشطة')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_active', true))
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
