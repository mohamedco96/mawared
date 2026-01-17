<?php

namespace App\Filament\Resources\EquityPeriodResource\Pages;

use App\Filament\Resources\EquityPeriodResource;
use Filament\Resources\Pages\ListRecords;

class ListEquityPeriods extends ListRecords
{
    protected static string $resource = EquityPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // We don't allow manual creation - periods are created automatically
        ];
    }
}
