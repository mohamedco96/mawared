<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\FixedAsset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepreciationService
{
    protected TreasuryService $treasuryService;

    public function __construct(TreasuryService $treasuryService)
    {
        $this->treasuryService = $treasuryService;
    }

    /**
     * Process monthly depreciation for all applicable assets
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
                ->get();

            $results = ['processed' => 0, 'skipped' => 0, 'errors' => []];

            foreach ($assets as $asset) {
                try {
                    // Check if asset is fully depreciated
                    $depreciableAmount = bcsub(
                        (string)$asset->purchase_amount,
                        (string)$asset->salvage_value,
                        4
                    );

                    if (bccomp((string)$asset->accumulated_depreciation, $depreciableAmount, 4) >= 0) {
                        $results['skipped']++;
                        continue;
                    }

                    // Calculate monthly depreciation
                    $monthlyDepreciation = $this->calculateAssetDepreciation($asset);

                    // Ensure we don't exceed depreciable amount
                    $remainingDepreciable = bcsub(
                        $depreciableAmount,
                        (string)$asset->accumulated_depreciation,
                        4
                    );

                    if (bccomp($monthlyDepreciation, $remainingDepreciable, 4) > 0) {
                        $monthlyDepreciation = $remainingDepreciable;
                    }

                    // Record depreciation transaction
                    $this->treasuryService->recordTransaction(
                        treasury_id: $asset->treasury_id,
                        type: TransactionType::DEPRECIATION_EXPENSE->value,
                        amount: -floatval($monthlyDepreciation), // Negative (expense)
                        description: "استهلاك {$asset->name} - " . $forMonth->format('M Y'),
                        partner_id: $asset->contributing_partner_id, // Track which partner's asset
                        reference_type: FixedAsset::class,
                        reference_id: $asset->id
                    );

                    // Update asset
                    $asset->accumulated_depreciation = bcadd(
                        (string)$asset->accumulated_depreciation,
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
     * Calculate monthly depreciation for a single asset
     */
    public function calculateAssetDepreciation(FixedAsset $asset): string
    {
        if (!$asset->useful_life_years || $asset->useful_life_years <= 0) {
            return '0';
        }

        // Straight-line: (Cost - Salvage) / (Useful Life in Months)
        $depreciableAmount = bcsub(
            (string)$asset->purchase_amount,
            (string)$asset->salvage_value,
            4
        );

        $totalMonths = $asset->useful_life_years * 12;

        return bcdiv($depreciableAmount, (string)$totalMonths, 4);
    }
}
