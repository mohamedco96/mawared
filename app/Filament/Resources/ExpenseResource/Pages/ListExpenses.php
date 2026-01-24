<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\ExpenseResource\Widgets\ExpenseStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ExpenseStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\Expense::count()),
            'today' => Tab::make('اليوم')
                ->icon('heroicon-m-calendar-days')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('expense_date', today()))
                ->badge(fn () => \App\Models\Expense::whereDate('expense_date', today())->count())
                ->badgeColor('info'),
            'month' => Tab::make('هذا الشهر')
                ->icon('heroicon-m-calendar')
                ->modifyQueryUsing(fn (Builder $query) => 
                    $query->whereMonth('expense_date', now()->month)
                          ->whereYear('expense_date', now()->year)
                )
                ->badge(fn () => \App\Models\Expense::whereMonth('expense_date', now()->month)
                        ->whereYear('expense_date', now()->year)->count())
                ->badgeColor('warning'),
        ];
    }
}
