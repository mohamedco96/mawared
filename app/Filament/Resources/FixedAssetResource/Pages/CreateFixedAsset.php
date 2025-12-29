<?php

namespace App\Filament\Resources\FixedAssetResource\Pages;

use App\Filament\Resources\FixedAssetResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFixedAsset extends CreateRecord
{
    protected static string $resource = FixedAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
