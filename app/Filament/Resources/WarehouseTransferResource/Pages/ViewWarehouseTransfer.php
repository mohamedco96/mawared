<?php

namespace App\Filament\Resources\WarehouseTransferResource\Pages;

use App\Filament\Resources\WarehouseTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseTransfer extends ViewRecord
{
    protected static string $resource = WarehouseTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => !$record->stockMovements()->exists()),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => !$record->stockMovements()->exists()),
        ];
    }
}
