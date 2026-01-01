<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\GeneralSetting;
use App\Models\FixedAsset;
use App\Models\Treasury;
use App\Services\TreasuryService;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existingValue = (float) GeneralSetting::getValue('fixed_assets_value', '0');

        // Only create record if there's a value
        if ($existingValue > 0) {
            $defaultTreasury = Treasury::first();

            if (!$defaultTreasury) {
                \Log::warning('No treasury found. Skipping fixed assets migration.');
                return;
            }

            // Determine earliest system date (for historical accuracy)
            $earliestDate = DB::table('treasury_transactions')
                ->min('created_at');

            if (!$earliestDate) {
                $earliestDate = now()->subYear(); // Default to 1 year ago if no transactions
            }

            // Create historical fixed asset record
            $asset = FixedAsset::create([
                'name' => 'أصول ثابتة مسجلة سابقاً',
                'description' => 'قيمة الأصول الثابتة المسجلة قبل تطبيق نظام الأصول الثابتة',
                'purchase_amount' => $existingValue,
                'treasury_id' => $defaultTreasury->id,
                'purchase_date' => $earliestDate,
                'created_by' => null,
            ]);

            // Auto-post it to maintain treasury transaction history
            $treasuryService = app(TreasuryService::class);
            $treasuryService->postFixedAssetPurchase($asset);

            \Log::info("✓ Migrated fixed assets value: {$existingValue} EGP");
            \Log::info("✓ Created FixedAsset record ID: {$asset->id}");
        } else {
            \Log::info('✓ No fixed assets value to migrate (value is 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find the migrated record
        $migratedAsset = FixedAsset::where('name', 'أصول ثابتة مسجلة سابقاً')->first();

        if ($migratedAsset) {
            // Note: Treasury transactions are preserved for audit trail
            // Only delete the asset itself
            $migratedAsset->forceDelete();

            \Log::info('✓ Rolled back fixed assets migration (treasury transactions preserved)');
        }
    }
};
