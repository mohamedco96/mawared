<?php

namespace App\Filament\Resources\ExpenseCategoryResource\Pages;

use App\Filament\Resources\ExpenseCategoryResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenseCategories extends ListRecords
{
    protected static string $resource = ExpenseCategoryResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\ExpenseCategory::count()),
            'operational' => Tab::make('تشغيلية')
                ->icon('heroicon-m-cog-6-tooth')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'operational'))
                ->badgeColor('primary'),
            'admin' => Tab::make('إدارية')
                ->icon('heroicon-m-building-office')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'admin'))
                ->badgeColor('info'),
            'marketing' => Tab::make('تسويقية')
                ->icon('heroicon-m-megaphone')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'marketing'))
                ->badgeColor('success'),
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
