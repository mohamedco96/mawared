<?php

namespace App\Filament\Resources\TreasuryTransactionResource\Pages;

use App\Filament\Resources\TreasuryTransactionResource;
use App\Filament\Resources\TreasuryTransactionResource\Widgets\TreasuryTransactionStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTreasuryTransactions extends ListRecords
{
    protected static string $resource = TreasuryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TreasuryTransactionStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\TreasuryTransaction::count()),
            'income' => Tab::make('مقبوضات')
                ->icon('heroicon-m-arrow-trending-up')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', ['capital_deposit', 'collection', 'partner_loan_receipt']))
                ->badge(fn () => \App\Models\TreasuryTransaction::whereIn('type', ['capital_deposit', 'collection', 'partner_loan_receipt'])->count())
                ->badgeColor('success'),
            'expense' => Tab::make('مدفوعات')
                ->icon('heroicon-m-arrow-trending-down')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('type', ['payment', 'expense', 'partner_drawing', 'employee_advance', 'salary_payment', 'partner_loan_repayment']))
                ->badge(fn () => \App\Models\TreasuryTransaction::whereIn('type', ['payment', 'expense', 'partner_drawing', 'employee_advance', 'salary_payment', 'partner_loan_repayment'])->count())
                ->badgeColor('danger'),
        ];
    }
}
