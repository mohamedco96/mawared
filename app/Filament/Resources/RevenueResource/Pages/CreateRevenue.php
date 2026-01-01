<?php

namespace App\Filament\Resources\RevenueResource\Pages;

use App\Filament\Resources\RevenueResource;
use App\Services\TreasuryService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateRevenue extends CreateRecord
{
    protected static string $resource = RevenueResource::class;

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $treasuryService = app(TreasuryService::class);
            $treasuryService->postRevenue($this->record);
        });
    }
}
