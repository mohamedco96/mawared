<?php

namespace App\Filament\Resources\TreasuryTransactionResource\Pages;

use App\Filament\Resources\TreasuryTransactionResource;
use App\Services\TreasuryService;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateTreasuryTransaction extends CreateRecord
{
    protected static string $resource = TreasuryTransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Use final_amount if discount was applied, otherwise use amount
        if (isset($data['final_amount']) && $data['final_amount'] > 0) {
            $data['amount'] = $data['final_amount'];
        }
        
        // Set amount sign based on type
        if ($data['type'] === 'payment') {
            $data['amount'] = -abs($data['amount']);
        } else {
            $data['amount'] = abs($data['amount']);
        }
        
        unset($data['final_amount'], $data['discount']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $treasuryService = app(TreasuryService::class);
        
        // Update partner balance if partner is involved
        if ($this->record->partner_id) {
            DB::transaction(function () use ($treasuryService) {
                $treasuryService->updatePartnerBalance($this->record->partner_id);
            });
        }
    }
}
