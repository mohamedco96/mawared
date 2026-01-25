<?php

namespace App\Services;

use App\Enums\ExpenseCategoryType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepreciationService
{
    /**
     * Process monthly depreciation for all applicable assets
     *
     * ACCOUNTING TREATMENT (Correct):
     * - Debit: Depreciation Expense (P&L) - recorded in Expense model
     * - Credit: Accumulated Depreciation (Contra-Asset) - recorded in FixedAsset model
     *
     * NOTE: Depreciation is a NON-CASH expense. It does NOT affect treasury balance.
     * We create an Expense record for P&L tracking, but NO TreasuryTransaction.
     */
    public function processMonthlyDepreciation(Carbon $forMonth): array
    {
        return DB::transaction(function () use ($forMonth) {
            $assets = FixedAsset::where('status', 'active')
                ->whereNotNull('useful_life_years')
                ->where(function ($q) use ($forMonth) {
                    // Either never depreciated OR last depreciation was before this month
                    $q->whereNull('last_depreciation_date')
                        ->orWhere('last_depreciation_date', '<', $forMonth->copy()->startOfMonth());
                })
                ->lockForUpdate() // Prevent concurrent depreciation processing
                ->get();

            $results = ['processed' => 0, 'skipped' => 0, 'errors' => []];

            // Get or create depreciation expense category
            $depreciationCategory = $this->getOrCreateDepreciationCategory();

            foreach ($assets as $asset) {
                try {
                    // Check if asset is fully depreciated
                    $depreciableAmount = bcsub(
                        (string) $asset->purchase_amount,
                        (string) ($asset->salvage_value ?? 0),
                        4
                    );

                    if (bccomp((string) $asset->accumulated_depreciation, $depreciableAmount, 4) >= 0) {
                        $results['skipped']++;
                        continue;
                    }

                    // Calculate monthly depreciation
                    $monthlyDepreciation = $this->calculateAssetDepreciation($asset);

                    // Ensure we don't exceed depreciable amount
                    $remainingDepreciable = bcsub(
                        $depreciableAmount,
                        (string) $asset->accumulated_depreciation,
                        4
                    );

                    if (bccomp($monthlyDepreciation, $remainingDepreciable, 4) > 0) {
                        $monthlyDepreciation = $remainingDepreciable;
                    }

                    // Idempotency check: prevent duplicate depreciation for same asset/period
                    $existingExpense = Expense::where('fixed_asset_id', $asset->id)
                        ->where('depreciation_period', $forMonth->copy()->startOfMonth()->format('Y-m-d'))
                        ->first();

                    if ($existingExpense) {
                        \Log::warning("Depreciation already recorded for asset {$asset->id} in period {$forMonth->format('Y-m')}. Skipping.");
                        $results['skipped']++;
                        continue;
                    }

                    // Create depreciation expense record (NON-CASH - no treasury transaction)
                    // This is the DEBIT side: Depreciation Expense (P&L)
                    Expense::create([
                        'title' => "استهلاك {$asset->name}",
                        'description' => "استهلاك شهري للأصل الثابت - " . $forMonth->format('M Y'),
                        'amount' => $monthlyDepreciation,
                        'treasury_id' => null, // NON-CASH expense - no treasury affected
                        'expense_date' => $forMonth->copy()->endOfMonth(),
                        'expense_category_id' => $depreciationCategory->id,
                        'is_non_cash' => true,
                        'fixed_asset_id' => $asset->id,
                        'depreciation_period' => $forMonth->copy()->startOfMonth(),
                        'created_by' => auth()->id(),
                    ]);

                    // Update asset accumulated depreciation
                    // This is the CREDIT side: Accumulated Depreciation (Contra-Asset)
                    $asset->accumulated_depreciation = bcadd(
                        (string) $asset->accumulated_depreciation,
                        $monthlyDepreciation,
                        4
                    );
                    $asset->last_depreciation_date = $forMonth->copy()->startOfMonth();
                    $asset->save();

                    $results['processed']++;
                } catch (\Exception $e) {
                    $results['errors'][] = "Asset {$asset->name}: " . $e->getMessage();
                }
            }

            return $results;
        });
    }

    /**
     * Get or create the depreciation expense category
     */
    protected function getOrCreateDepreciationCategory(): ExpenseCategory
    {
        return ExpenseCategory::firstOrCreate(
            ['type' => ExpenseCategoryType::DEPRECIATION->value],
            [
                'name' => 'استهلاك الأصول الثابتة',
                'is_active' => true,
            ]
        );
    }

    /**
     * Calculate monthly depreciation for a single asset
     */
    public function calculateAssetDepreciation(FixedAsset $asset): string
    {
        if (! $asset->useful_life_years || $asset->useful_life_years <= 0) {
            return '0';
        }

        // Straight-line: (Cost - Salvage) / (Useful Life in Months)
        $depreciableAmount = bcsub(
            (string) $asset->purchase_amount,
            (string) ($asset->salvage_value ?? 0),
            4
        );

        $totalMonths = $asset->useful_life_years * 12;

        return bcdiv($depreciableAmount, (string) $totalMonths, 4);
    }
}
