<?php

namespace App\Filament\Resources\WarehouseTransferResource\Pages;

use App\Filament\Resources\WarehouseTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseTransfer extends EditRecord
{
    protected static string $resource = WarehouseTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
