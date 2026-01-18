<?php

namespace App\Filament\Resources\TreasuryTransactionResource\Pages;

use App\Enums\TransactionType;
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

        // Set amount sign based on type using enum
        if (isset($data['type'])) {
            $typeEnum = TransactionType::from($data['type']);
            $sign = $typeEnum->getSign();
            $data['amount'] = $sign * abs($data['amount']);
        }

        // Set reference_type for collection/payment/loan transactions with partners
        // This is CRITICAL for partner balance calculations
        if (in_array($data['type'], ['collection', 'payment', 'partner_loan_receipt', 'partner_loan_repayment']) && !empty($data['partner_id'])) {
            $data['reference_type'] = 'financial_transaction';
            $data['reference_id'] = null;
        }

        // Clean up virtual and form-only fields
        unset($data['final_amount'], $data['discount'], $data['current_balance_display'], $data['employee_advance_balance_display'], $data['employee_salary_display'], $data['transaction_category']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $treasuryService = app(TreasuryService::class);

        // Update partner balance if partner is involved
        if ($this->record->partner_id) {
            DB::transaction(function () use ($treasuryService) {
                $partner = \App\Models\Partner::find($this->record->partner_id);

                if ($partner && $partner->type === 'shareholder') {
                    // For capital-related transactions, update current_capital and trigger equity recalculation
                    if (in_array($this->record->type, ['capital_deposit', 'partner_drawing', 'asset_contribution'])) {
                        $partner->recalculateCapital();

                        // Recalculate equity percentages for all shareholders
                        $capitalService = app(\App\Services\CapitalService::class);
                        $capitalService->recalculateEquityPercentages();

                        // Reload partner to get updated equity percentage
                        $partner->refresh();

                        // Auto-create initial period ONLY if none exists (first shareholder case)
                        $currentPeriod = $capitalService->getCurrentPeriod();
                        if (!$currentPeriod) {
                            $shareholders = \App\Models\Partner::where('type', 'shareholder')->get();
                            if ($shareholders->isNotEmpty()) {
                                $capitalService->createInitialPeriod(now(), $shareholders->all());
                            }
                        } else {
                            // If period exists, add new shareholders to it and update percentages
                            // Check if this partner is already in the current period
                            $partnerInPeriod = \App\Models\EquityPeriodPartner::where('equity_period_id', $currentPeriod->id)
                                ->where('partner_id', $partner->id)
                                ->exists();

                            if (!$partnerInPeriod && in_array($this->record->type, ['capital_deposit', 'asset_contribution'])) {
                                // New shareholder joining - add to current period with current capital
                                \App\Models\EquityPeriodPartner::create([
                                    'equity_period_id' => $currentPeriod->id,
                                    'partner_id' => $partner->id,
                                    'equity_percentage' => $partner->equity_percentage ?? 0,
                                    'capital_at_start' => $partner->current_capital ?? 0,
                                ]);
                            }
                        }
                    }
                } else {
                    // For customers/suppliers, update balance as normal
                    $treasuryService->updatePartnerBalance($this->record->partner_id);
                }
            });
        }

        // REMOVED: Employee advance balance update
        // This is now handled by TreasuryService::recordEmployeeAdvance()
        // to prevent double incrementing (CRITICAL FIX)
    }
}
