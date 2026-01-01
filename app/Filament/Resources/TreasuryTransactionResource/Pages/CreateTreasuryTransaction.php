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
        if (in_array($data['type'], ['payment', 'partner_drawing', 'employee_advance'])) {
            $data['amount'] = -abs($data['amount']);
        } else {
            $data['amount'] = abs($data['amount']);
        }

        // Set reference_type for collection/payment transactions with partners
        // This is CRITICAL for partner balance calculations
        if (in_array($data['type'], ['collection', 'payment']) && !empty($data['partner_id'])) {
            $data['reference_type'] = 'financial_transaction';
            $data['reference_id'] = null;
        }

        unset($data['final_amount'], $data['discount'], $data['current_balance_display'], $data['employee_advance_balance_display']);

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

        // REMOVED: Employee advance balance update
        // This is now handled by TreasuryService::recordEmployeeAdvance()
        // to prevent double incrementing (CRITICAL FIX)
    }
}
