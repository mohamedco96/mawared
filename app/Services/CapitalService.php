<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\EquityPeriod;
use App\Models\EquityPeriodPartner;
use App\Models\Expense;
use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CapitalService
{
    protected TreasuryService $treasuryService;

    public function __construct(TreasuryService $treasuryService)
    {
        $this->treasuryService = $treasuryService;
    }

    /**
     * Get the currently open equity period
     */
    public function getCurrentPeriod(): ?EquityPeriod
    {
        return EquityPeriod::open()->first();
    }

    /**
     * Create initial equity period with starting capital percentages
     */
    public function createInitialPeriod(Carbon $startDate, array $partners): EquityPeriod
    {
        return DB::transaction(function () use ($startDate, $partners) {
            // Validate percentages sum to ~100%
            $totalPercentage = collect($partners)->sum('equity_percentage');
            if (abs($totalPercentage - 100) > 0.01) {
                throw new \Exception("Equity percentages must sum to 100% (got {$totalPercentage}%)");
            }

            // Create period
            $period = EquityPeriod::create([
                'period_number' => 1,
                'start_date' => $startDate,
                'status' => 'open',
            ]);

            // Lock partner percentages
            foreach ($partners as $partner) {
                if ($partner instanceof Partner) {
                    EquityPeriodPartner::create([
                        'equity_period_id' => $period->id,
                        'partner_id' => $partner->id,
                        'equity_percentage' => $partner->equity_percentage ?? 0,
                        'capital_at_start' => $partner->current_capital ?? 0,
                    ]);
                }
            }

            return $period;
        });
    }

    /**
     * Calculate net profit for a period
     */
    public function calculatePeriodProfit(EquityPeriod $period): float
    {
        $startDate = $period->start_date->copy();
        $endDate = $period->end_date ? $period->end_date->copy() : now();

        // Revenue: Sales - Sales Returns
        $salesRevenue = SalesInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $salesReturns = SalesReturn::where('status', 'posted')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $revenueOther = Revenue::whereBetween('revenue_date', [$startDate, $endDate])
            ->sum('amount');

        $totalRevenue = $salesRevenue - $salesReturns + $revenueOther;

        // Expenses: Purchases - Purchase Returns + Operating Expenses + Manager Salaries + Depreciation
        $purchases = PurchaseInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $purchaseReturns = PurchaseReturn::where('status', 'posted')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('total');

        $operatingExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->sum('amount');

        // Manager salaries (from salary_payment transactions)
        // For now, we'll count all salary payments as expenses
        // In future, could filter by partner_id if needed
        $managerSalaries = TreasuryTransaction::where('type', TransactionType::SALARY_PAYMENT->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw('ABS(amount)'));

        // Depreciation expenses
        $depreciation = TreasuryTransaction::where('type', TransactionType::DEPRECIATION_EXPENSE->value)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum(DB::raw('ABS(amount)'));

        $totalExpenses = $purchases - $purchaseReturns + $operatingExpenses + $managerSalaries + $depreciation;

        // Calculate net profit
        $netProfit = $totalRevenue - $totalExpenses;

        // Store in period for reference
        $period->update([
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
        ]);

        return $netProfit;
    }

    /**
     * Allocate profit to partners based on locked percentages
     */
    public function allocateProfitToPartners(EquityPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            // Calculate profit
            $netProfit = $this->calculatePeriodProfit($period);

            // Get partners with locked percentages
            $periodPartners = EquityPeriodPartner::where('equity_period_id', $period->id)
                ->with('partner')
                ->get();

            foreach ($periodPartners as $periodPartner) {
                $partner = $periodPartner->partner;
                $percentage = floatval($periodPartner->equity_percentage);

                // Calculate profit share
                $profitShare = $netProfit * ($percentage / 100);

                // Create profit allocation transaction
                $this->treasuryService->recordTransaction(
                    Treasury::where('type', 'cash')->first()->id,
                    TransactionType::PROFIT_ALLOCATION->value,
                    (string)$profitShare, // Positive (increases capital)
                    "توزيع أرباح - الفترة #{$period->period_number}",
                    $partner->id,
                    EquityPeriod::class,
                    $period->id
                );

                // Update partner's current_capital
                $partner->current_capital = bcadd((string)$partner->current_capital, (string)$profitShare, 4);
                $partner->save();

                // Update period partner record
                $periodPartner->profit_allocated = $profitShare;
                $periodPartner->save();
            }
        });
    }

    /**
     * Close current period and allocate profit
     * Automatically creates a new period after closing
     */
    public function closePeriodAndAllocate(?Carbon $endDate = null, ?string $notes = null): EquityPeriod
    {
        return DB::transaction(function () use ($endDate, $notes) {
            // Get current open period
            $period = $this->getCurrentPeriod();

            if (!$period) {
                throw new \Exception('No open period found to close');
            }

            // Set end date (defaults to now if not provided)
            $period->end_date = $endDate ?? now();

            // Calculate and allocate profit
            $this->allocateProfitToPartners($period);

            // Close the period
            $period->status = 'closed';
            $period->closed_by = auth()->id();
            $period->closed_at = now();
            $period->notes = $notes;
            $period->save();

            // Automatically create new period starting immediately after (same moment)
            $this->createNewPeriod($period->end_date->copy()->addSecond());

            return $period;
        });
    }

    /**
     * Inject capital into a partner's account
     */
    public function injectCapital(Partner $partner, float $amount, string $type = 'cash', ?array $metadata = null): void
    {
        DB::transaction(function () use ($partner, $amount, $type, $metadata) {
            // Step 1: Record the capital injection
            // For cash contributions, record a treasury transaction
            // For asset contributions, we don't record cash treasury transaction (the asset itself is tracked separately)
            if ($type === 'cash') {
                $this->treasuryService->recordTransaction(
                    $metadata['treasury_id'] ?? Treasury::where('type', 'cash')->first()->id,
                    TransactionType::CAPITAL_DEPOSIT->value,
                    (string)$amount, // Positive (increases capital)
                    $metadata['description'] ?? "Capital injection by {$partner->name}",
                    $partner->id,
                    $metadata['reference_type'] ?? null,
                    $metadata['reference_id'] ?? null
                );
            }
            // Note: Asset contributions are tracked via the FixedAsset model, not cash treasury

            // Step 2: Update partner's current_capital
            $newCapital = bcadd((string)$partner->current_capital, (string)$amount, 4);
            $partner->current_capital = $newCapital;
            $partner->save();

            // Step 3: Recalculate equity percentages for all shareholders
            $newPercentages = $this->recalculateEquityPercentages();
            $newEquityPercentage = $newPercentages[$partner->id] ?? 0;

            // Step 4: Check if we need to create initial period or update existing period
            $currentPeriod = $this->getCurrentPeriod();

            if (!$currentPeriod) {
                // No open period exists - create the first one
                $this->createNewPeriod(now());
            } else {
                // Update the existing period's partner percentages
                // First, check if this partner already has a record in the current period
                $periodPartner = EquityPeriodPartner::where('equity_period_id', $currentPeriod->id)
                    ->where('partner_id', $partner->id)
                    ->first();

                if ($periodPartner) {
                    // Update existing record
                    $periodPartner->equity_percentage = $newEquityPercentage;
                    $periodPartner->capital_at_start = $newCapital; // Update to reflect new capital
                    $periodPartner->capital_injected = bcadd((string)$periodPartner->capital_injected, (string)$amount, 4);
                    $periodPartner->save();
                } else {
                    // Create new record for this partner in the current period
                    // Use the values we just calculated (no need to re-fetch from DB)
                    EquityPeriodPartner::create([
                        'equity_period_id' => $currentPeriod->id,
                        'partner_id' => $partner->id,
                        'equity_percentage' => $newEquityPercentage,
                        'capital_at_start' => $newCapital,
                        'capital_injected' => $amount,
                    ]);
                }

                // Update all other partners' percentages in the current period
                foreach ($newPercentages as $partnerId => $percentage) {
                    if ($partnerId !== $partner->id) {
                        $otherPeriodPartner = EquityPeriodPartner::where('equity_period_id', $currentPeriod->id)
                            ->where('partner_id', $partnerId)
                            ->first();

                        if ($otherPeriodPartner) {
                            $otherPeriodPartner->equity_percentage = $percentage;
                            $otherPeriodPartner->save();
                        }
                    }
                }
            }
        });
    }

    /**
     * Record partner drawing (withdrawal)
     */
    public function recordDrawing(Partner $partner, float $amount, ?string $description = null): void
    {
        DB::transaction(function () use ($partner, $amount, $description) {
            // Record transaction
            $this->treasuryService->recordTransaction(
                Treasury::where('type', 'cash')->first()->id,
                TransactionType::PARTNER_DRAWING->value,
                (string)(-$amount), // Negative (reduces treasury)
                $description ?? "Partner drawing - {$partner->name}",
                $partner->id
            );

            // Update capital
            $partner->current_capital = bcsub((string)$partner->current_capital, (string)$amount, 4);
            $partner->save();

            // Record in current period
            $period = $this->getCurrentPeriod();
            if ($period) {
                $pivotRecord = EquityPeriodPartner::where('equity_period_id', $period->id)
                    ->where('partner_id', $partner->id)
                    ->first();

                if ($pivotRecord) {
                    $pivotRecord->drawings_taken = bcadd((string)$pivotRecord->drawings_taken, (string)$amount, 4);
                    $pivotRecord->save();
                }
            }
        });
    }

    /**
     * Recalculate equity percentages for all shareholders
     */
    public function recalculateEquityPercentages(): array
    {
        $shareholders = Partner::where('type', 'shareholder')->get();
        $totalCapital = $shareholders->sum('current_capital');

        if ($totalCapital <= 0) {
            throw new \Exception('Total capital must be positive to calculate percentages');
        }

        $percentages = [];
        foreach ($shareholders as $partner) {
            $percentage = ($partner->current_capital / $totalCapital) * 100;
            $partner->equity_percentage = $percentage;
            $partner->save();
            $percentages[$partner->id] = $percentage;
        }

        return $percentages;
    }

    /**
     * Create a new equity period with current percentages
     */
    public function createNewPeriod(Carbon $startDate): EquityPeriod
    {
        return DB::transaction(function () use ($startDate) {
            // Close any existing open period first
            $openPeriod = EquityPeriod::where('status', 'open')->first();
            if ($openPeriod) {
                // Only update end_date if it would be valid (after start_date)
                $proposedEndDate = $startDate->copy()->subSecond();
                if ($proposedEndDate->gte($openPeriod->start_date)) {
                    $openPeriod->update([
                        'status' => 'closed',
                        'end_date' => $proposedEndDate
                    ]);
                } else {
                    // If proposed end date is before start date, set them equal
                    $openPeriod->update([
                        'status' => 'closed',
                        'end_date' => $openPeriod->start_date
                    ]);
                }
            }

            // Create new period
            $period = EquityPeriod::create([
                'period_number' => (EquityPeriod::max('period_number') ?? 0) + 1,
                'start_date' => $startDate,
                'status' => 'open',
            ]);

            // Lock current percentages
            $this->lockPartnerPercentages($period);

            return $period;
        });
    }

    /**
     * Lock current partner percentages for a period
     */
    public function lockPartnerPercentages(EquityPeriod $period): void
    {
        $shareholders = Partner::where('type', 'shareholder')->get();

        foreach ($shareholders as $partner) {
            EquityPeriodPartner::create([
                'equity_period_id' => $period->id,
                'partner_id' => $partner->id,
                'equity_percentage' => $partner->equity_percentage ?? 0,
                'capital_at_start' => $partner->current_capital ?? 0,
            ]);
        }
    }

    /**
     * Get capital ledger for a partner
     */
    public function getPartnerCapitalLedger(Partner $partner, ?Carbon $from = null, ?Carbon $to = null): \Illuminate\Support\Collection
    {
        $query = TreasuryTransaction::where('partner_id', $partner->id)
            ->whereIn('type', [
                TransactionType::CAPITAL_DEPOSIT->value,
                TransactionType::ASSET_CONTRIBUTION->value,
                TransactionType::PROFIT_ALLOCATION->value,
                TransactionType::PARTNER_DRAWING->value,
            ])
            ->orderBy('created_at', 'desc');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        return $query->get();
    }
}
