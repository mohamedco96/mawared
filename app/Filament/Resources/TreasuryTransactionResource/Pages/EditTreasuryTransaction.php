<?php

namespace App\Filament\Resources\TreasuryTransactionResource\Pages;

use App\Filament\Resources\TreasuryTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTreasuryTransaction extends EditRecord
{
    protected static string $resource = TreasuryTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
