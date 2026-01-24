<?php

namespace App\Filament\Resources\InstallmentResource\Pages;

use App\Filament\Resources\InstallmentResource;
use App\Filament\Resources\InstallmentResource\Widgets\InstallmentStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInstallments extends ListRecords
{
    protected static string $resource = InstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InstallmentStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\Installment::count()),
            'pending' => Tab::make('قيد الانتظار')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => \App\Models\Installment::where('status', 'pending')->count())
                ->badgeColor('warning'),
            'overdue' => Tab::make('متأخرة')
                ->icon('heroicon-m-exclamation-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(function ($q) {
                    $q->where('status', 'overdue')
                        ->orWhere(function ($subQ) {
                            $subQ->where('status', '!=', 'paid')
                                ->where('due_date', '<', now());
                        });
                })
                )
                ->badge(fn () => \App\Models\Installment::where(function ($q) {
                    $q->where('status', 'overdue')
                        ->orWhere(function ($subQ) {
                            $subQ->where('status', '!=', 'paid')
                                ->where('due_date', '<', now());
                        });
                })->count()
                )
                ->badgeColor('danger'),
            'paid' => Tab::make('مدفوعة')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge(fn () => \App\Models\Installment::where('status', 'paid')->count())
                ->badgeColor('success'),
        ];
    }
}
