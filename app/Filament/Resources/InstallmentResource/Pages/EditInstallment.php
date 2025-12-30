<?php

namespace App\Filament\Resources\InstallmentResource\Pages;

use App\Filament\Resources\InstallmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstallment extends EditRecord
{
    protected static string $resource = InstallmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
