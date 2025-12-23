<?php

namespace App\Filament\Resources\TreasuryTransactionResource\Pages;

use App\Filament\Resources\TreasuryTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTreasuryTransactions extends ListRecords
{
    protected static string $resource = TreasuryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
