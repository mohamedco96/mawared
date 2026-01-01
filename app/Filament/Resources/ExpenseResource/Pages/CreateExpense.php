<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\TreasuryService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    protected function afterCreate(): void
    {
        DB::transaction(function () {
            $treasuryService = app(TreasuryService::class);
            $treasuryService->postExpense($this->record);
        });
    }
}
