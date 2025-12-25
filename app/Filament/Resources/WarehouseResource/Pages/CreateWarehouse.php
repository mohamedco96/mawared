<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Resources\WarehouseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = 'WH-' . strtoupper(uniqid());
        }

        return $data;
    }
}
