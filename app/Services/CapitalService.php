<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\EquityPeriod;
use App\Models\EquityPeriodPartner;
use App\Models\Expense;
use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
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
     * Get the default cash treasury for capital transactions
     *
     * BLIND-14 FIX: Proper null handling for treasury selection
     * @throws \Exception If no cash treasury exists
     */
    protected function getDefaultCashTreasury(): Treasury
    {
        $treasury = Treasury::where('type', 'cash')->first();

        if (! $treasury) {
            throw new \Exception('لا توجد خزينة نقدية متاحة. يرجى إنشاء خزينة نقدية أولاً.');
        }

        return $treasury;
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
     * Get calculated financial summary for a period (without saving)
     * Uses proper Accrual Basis Accounting with COGS instead of Purchase Invoices.
     *
     * Net Profit = Total Revenue - (COGS + Operating Expenses + Commissions + Discounts Allowed + Salaries + Depreciation)
     */
    public function getFinancialSummary(EquityPeriod $period): array
    {
        // Use exact timestamps for period boundaries
        $startDateTime = $period->start_date->copy();
        $endDateTime = $period->end_date ? $period->end_date->copy() : now();

        // ============================================================
        // REVENUE CALCULATION
        // ============================================================

        // Sales revenue from posted invoices
        $salesRevenue = SalesInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('total');

        // Sales returns reduce revenue (Accrual Basis - matches FinancialReportService)
        $salesReturns = SalesReturn::where('status', 'posted')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('total');

        // Other revenue (non-sales income)
        $revenueOther = Revenue::whereBetween('revenue_date', [$startDateTime, $endDateTime])
            ->sum('amount');

        $totalRevenue = bcsub(bcadd((string) $salesRevenue, (string) $revenueOther, 4), (string) $salesReturns, 4);

        // ============================================================
        // EXPENSE CALCULATION (Accrual Basis with COGS)
        // ============================================================

        // COGS: Cost of Goods Sold (Gross - Returned)
        // Gross COGS from posted sales invoices in the period
        $grossCOGS = SalesInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('cost_total');

        // Returned COGS from posted sales returns in the period
        // Uses SalesReturn.created_at (when return happened), not original invoice date
        $returnedCOGS = SalesReturn::where('status', 'posted')
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('cost_total');

        // Net COGS = Gross COGS - Returned COGS (Periodicity Principle)
        $cogs = bcsub((string) $grossCOGS, (string) $returnedCOGS, 4);

        // Operating expenses from Expense model (exclude non-cash expenses like depreciation)
        $operatingExpenses = Expense::whereBetween('expense_date', [$startDateTime, $endDateTime])
            ->where('is_non_cash', false)
            ->sum('amount');

        // Commissions paid to salespeople (for consistency with FinancialReportService)
        $commissionPayouts = TreasuryTransaction::where('type', TransactionType::COMMISSION_PAYOUT->value)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum(DB::raw('ABS(amount)')); // Stored as negative, use ABS

        $commissionReversals = TreasuryTransaction::where('type', TransactionType::COMMISSION_REVERSAL->value)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum('amount'); // Stored as positive

        $netCommissions = bcsub((string) $commissionPayouts, (string) $commissionReversals, 4);

        // Settlement discounts allowed (payment-time discounts given to customers)
        // Uses payment_date for consistency with FinancialReportService
        $discountAllowed = InvoicePayment::whereHasMorph('payable', [SalesInvoice::class])
            ->whereDate('payment_date', '>=', $startDateTime)
            ->whereDate('payment_date', '<=', $endDateTime)
            ->sum('discount');

        // Manager salaries (from salary_payment transactions)
        $managerSalaries = TreasuryTransaction::where('type', TransactionType::SALARY_PAYMENT->value)
            ->whereBetween('created_at', [$startDateTime, $endDateTime])
            ->sum(DB::raw('ABS(amount)'));

        // Depreciation expenses (from Expense model, not Treasury - depreciation is NON-CASH)
        $depreciation = Expense::where('is_non_cash', true)
            ->whereNotNull('fixed_asset_id')
            ->whereBetween('expense_date', [$startDateTime, $endDateTime])
            ->sum('amount');

        // Total Expenses = COGS + Operating Expenses + Commissions + Discounts + Salaries + Depreciation
        $totalExpenses = bcadd(
            bcadd(
                bcadd(
                    bcadd(
                        bcadd((string) $cogs, (string) $operatingExpenses, 4),
                        (string) $netCommissions,
                        4
                    ),
                    (string) $discountAllowed,
                    4
                ),
                (string) $managerSalaries,
                4
            ),
            (string) $depreciation,
            4
        );

        // ============================================================
        // PROFIT CALCULATION
        // ============================================================

        $netSales = bcsub((string) $salesRevenue, (string) $salesReturns, 4);
        $grossProfit = bcsub((string) $netSales, (string) $cogs, 4);
        
        // Net Profit = Total Revenue - Total Expenses
        // Note: Total Revenue includes Other Revenue, while Gross Profit does not.
        $netProfit = bcsub((string) $totalRevenue, (string) $totalExpenses, 4);

        return [
            // Revenue breakdown
            'total_revenue' => (float) $totalRevenue,
            'sales_revenue' => (float) $salesRevenue,
            'sales_returns' => (float) $salesReturns,
            'other_revenue' => (float) $revenueOther,

            // Expense breakdown
            'total_expenses' => (float) $totalExpenses,
            'cogs' => (float) $cogs,
            'operating_expenses' => (float) $operatingExpenses,
            'commissions' => (float) $netCommissions,
            'discount_allowed' => (float) $discountAllowed,
            'manager_salaries' => (float) $managerSalaries,
            'depreciation' => (float) $depreciation,

            // Profit metrics
            'gross_profit' => (float) $grossProfit,
            'net_profit' => (float) $netProfit,
        ];
    }

    /**
     * Calculate net profit for a period and save to database
     */
    public function calculatePeriodProfit(EquityPeriod $period): float
    {
        $summary = $this->getFinancialSummary($period);

        // Store in period for reference
        $period->update([
            'total_revenue' => $summary['total_revenue'],
            'total_expenses' => $summary['total_expenses'],
            'net_profit' => $summary['net_profit'],
        ]);

        return $summary['net_profit'];
    }

    /**
     * Allocate profit to partners based on locked percentages
     *
     * CRITICAL-13 FIX: Uses BC Math for precise profit share calculation
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

            // Get default treasury with null safety
            $treasury = Treasury::where('type', 'cash')->first();
            if (! $treasury) {
                throw new \Exception('لا يوجد خزينة نقدية. يرجى إنشاء خزينة أولاً.');
            }

            foreach ($periodPartners as $periodPartner) {
                $partner = $periodPartner->partner;

                // CRITICAL-13 FIX: Use BC Math for profit share calculation
                // profitShare = netProfit * (percentage / 100)
                $percentageDecimal = bcdiv((string) $periodPartner->equity_percentage, '100', 6);
                $profitShare = bcmul((string) $netProfit, $percentageDecimal, 4);

                // Create profit allocation transaction
                $this->treasuryService->recordTransaction(
                    $treasury->id,
                    TransactionType::PROFIT_ALLOCATION->value,
                    $profitShare, // Already a string from bcmul
                    "توزيع أرباح - الفترة #{$period->period_number}",
                    $partner->id,
                    EquityPeriod::class,
                    $period->id
                );

                // Update partner's current_capital
                $partner->current_capital = bcadd((string) $partner->current_capital, $profitShare, 4);
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

            if (! $period) {
                throw new \Exception('No open period found to close');
            }

            // Set end date to exact timestamp (defaults to now if not provided)
            $period->end_date = $endDate ?? now();

            // Calculate and allocate profit
            $this->allocateProfitToPartners($period);

            // Close the period
            $period->status = 'closed';
            $period->closed_by = auth()->id();
            $period->closed_at = now();
            $period->notes = $notes;
            $period->save();

            // Create new period starting exactly 1 second after the closed period ends
            $this->createNewPeriod($period->end_date->copy()->addSecond());

            return $period;
        });
    }

    /**
     * Inject capital into a partner's account
     *
     * BLIND-15 FIX: Added validation to ensure partner is a shareholder
     */
    public function injectCapital(Partner $partner, float $amount, string $type = 'cash', ?array $metadata = null): void
    {
        // BLIND-15 FIX: Validate partner is a shareholder before capital injection
        if ($partner->type !== 'shareholder') {
            throw new \Exception("لا يمكن إضافة رأس مال لغير الشركاء. نوع الشريك: {$partner->type}");
        }

        DB::transaction(function () use ($partner, $amount, $type, $metadata) {
            // Step 1: Record the capital injection
            // For cash contributions, record a treasury transaction
            // For asset contributions, we don't record cash treasury transaction (the asset itself is tracked separately)
            if ($type === 'cash') {
                // BLIND-14 FIX: Use helper method for safe treasury selection
                $treasuryId = $metadata['treasury_id'] ?? $this->getDefaultCashTreasury()->id;

                $this->treasuryService->recordTransaction(
                    $treasuryId,
                    TransactionType::CAPITAL_DEPOSIT->value,
                    (string) $amount, // Positive (increases capital)
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
                    // Update existing record - only update equity_percentage and capital_injected
                    // capital_at_start should NOT be changed (it's the capital at period START)
                    $periodPartner->equity_percentage = $newEquityPercentage;
                    $periodPartner->capital_injected = bcadd((string)$periodPartner->capital_injected, (string)$amount, 4);
                    $periodPartner->save();
                } else {
                    // Create new record for this partner in the current period
                    // capital_at_start = capital BEFORE this injection
                    $capitalAtStart = bcsub((string)$newCapital, (string)$amount, 4);
                    EquityPeriodPartner::create([
                        'equity_period_id' => $currentPeriod->id,
                        'partner_id' => $partner->id,
                        'equity_percentage' => $newEquityPercentage,
                        'capital_at_start' => $capitalAtStart,
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
     *
     * BLIND-15 FIX: Added validation to ensure partner is a shareholder
     */
    public function recordDrawing(Partner $partner, float $amount, ?string $description = null): void
    {
        // BLIND-15 FIX: Validate partner is a shareholder before recording drawing
        if ($partner->type !== 'shareholder') {
            throw new \Exception("لا يمكن تسجيل سحب لغير الشركاء. نوع الشريك: {$partner->type}");
        }

        DB::transaction(function () use ($partner, $amount, $description) {
            // BLIND-14 FIX: Use helper method for safe treasury selection
            $this->treasuryService->recordTransaction(
                $this->getDefaultCashTreasury()->id,
                TransactionType::PARTNER_DRAWING->value,
                (string) (-$amount), // Negative (reduces treasury)
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
     *
     * CRITICAL-14 FIX: Uses BC Math for precise percentage calculation
     * BLIND-13 FIX: Added locking to prevent race conditions during recalculation
     */
    public function recalculateEquityPercentages(): array
    {
        return DB::transaction(function () {
            // BLIND-13 FIX: Lock all shareholders to prevent concurrent modifications
            $shareholders = Partner::where('type', 'shareholder')
                ->lockForUpdate()
                ->get();

            // Calculate total capital using BC Math
            $totalCapital = '0';
            foreach ($shareholders as $partner) {
                $totalCapital = bcadd($totalCapital, (string) $partner->current_capital, 4);
            }

            if (bccomp($totalCapital, '0', 4) <= 0) {
                throw new \Exception('Total capital must be positive to calculate percentages');
            }

            $percentages = [];
            foreach ($shareholders as $partner) {
                // CRITICAL-14 FIX: Use BC Math for percentage calculation
                // percentage = (current_capital / totalCapital) * 100
                $ratio = bcdiv((string) $partner->current_capital, $totalCapital, 6);
                $percentage = bcmul($ratio, '100', 4);

                $partner->equity_percentage = $percentage;
                $partner->save();
                $percentages[$partner->id] = (float) $percentage;
            }

            return $percentages;
        });
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
