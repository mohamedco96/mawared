<?php

namespace Database\Seeders;

use App\Models\FixedAsset;
use App\Models\Treasury;
use App\Services\TreasuryService;
use Illuminate\Database\Seeder;

class FixedAssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $treasury = Treasury::first();

        if (!$treasury) {
            $this->command->error('No treasury found. Run TreasurySeeder first.');
            return;
        }

        $assets = [
            [
                'name' => 'أثاث مكتبي',
                'description' => 'مكاتب وكراسي ومقاعد للموظفين',
                'purchase_amount' => 15000.00,
                'purchase_date' => now()->subMonths(6),
            ],
            [
                'name' => 'أجهزة كمبيوتر',
                'description' => '5 أجهزة كمبيوتر مكتبية للموظفين',
                'purchase_amount' => 25000.00,
                'purchase_date' => now()->subMonths(4),
            ],
            [
                'name' => 'مكيفات هواء',
                'description' => '3 وحدات تكييف للمكتب',
                'purchase_amount' => 12000.00,
                'purchase_date' => now()->subMonths(2),
            ],
            [
                'name' => 'طابعة ليزر',
                'description' => 'طابعة ليزر ملونة عالية الجودة',
                'purchase_amount' => 8000.00,
                'purchase_date' => now()->subMonths(3),
            ],
            [
                'name' => 'نظام كاميرات مراقبة',
                'description' => 'نظام كاميرات مراقبة متكامل للمكتب',
                'purchase_amount' => 18000.00,
                'purchase_date' => now()->subMonths(5),
            ],
        ];

        $treasuryService = app(TreasuryService::class);

        foreach ($assets as $assetData) {
            $asset = FixedAsset::create(array_merge($assetData, [
                'treasury_id' => $treasury->id,
                'created_by' => null,
            ]));

            // Auto-post for demo purposes (skip if insufficient balance)
            try {
                $treasuryService->postFixedAssetPurchase($asset);
                $this->command->info("✓ Created and posted fixed asset: {$asset->name} ({$asset->purchase_amount} EGP)");
            } catch (\Exception $e) {
                $this->command->warn("✓ Created fixed asset (not posted - insufficient balance): {$asset->name} ({$asset->purchase_amount} EGP)");
            }
        }

        $totalValue = FixedAsset::sum('purchase_amount');
        $this->command->info("✓ Total Fixed Assets Value: {$totalValue} EGP");
    }
}
