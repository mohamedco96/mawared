<?php

namespace Database\Seeders;

use App\Models\FixedAsset;
use App\Models\Partner;
use App\Models\Treasury;
use App\Services\CapitalService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class CapitalManagementTestSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('🚀 Creating test data for Capital Management...');

        // Step 1: Create two shareholders
        $this->command->info('📝 Creating shareholders...');

        $partnerA = Partner::create([
            'name' => 'محمد أحمد (شريك A)',
            'type' => 'shareholder',
            'current_capital' => 150000,
            'equity_percentage' => 60,
            'is_manager' => false,
        ]);

        $partnerB = Partner::create([
            'name' => 'أحمد علي (شريك B - مدير)',
            'type' => 'shareholder',
            'current_capital' => 100000,
            'equity_percentage' => 40,
            'is_manager' => true,
            'monthly_salary' => 12000,
        ]);

        $this->command->info("✓ Partner A: {$partnerA->name} - 150,000 ج.م (60%)");
        $this->command->info("✓ Partner B: {$partnerB->name} - 100,000 ج.م (40%) - Manager with 12,000 ج.م/month");

        // Step 2: Record initial capital as transactions
        $this->command->info('💰 Recording initial capital transactions...');

        $treasury = Treasury::where('type', 'cash')->first();

        if (!$treasury) {
            $this->command->error('❌ No cash treasury found! Please create one first.');
            return;
        }

        app(\App\Services\TreasuryService::class)->recordTransaction(
            $treasury->id,
            'capital_deposit',
            '150000',
            'رأس مال مبدئي - محمد أحمد',
            $partnerA->id
        );

        app(\App\Services\TreasuryService::class)->recordTransaction(
            $treasury->id,
            'capital_deposit',
            '100000',
            'رأس مال مبدئي - أحمد علي',
            $partnerB->id
        );

        $this->command->info('✓ Capital transactions recorded');

        // Step 3: Create initial equity period
        $this->command->info('📊 Creating initial equity period...');

        $capitalService = app(CapitalService::class);

        $period = $capitalService->createInitialPeriod(
            Carbon::now()->subMonths(3),
            [$partnerA, $partnerB]
        );

        $this->command->info("✓ Period #{$period->period_number} created (Started: {$period->start_date->format('Y-m-d')})");

        // Step 4: Add Partner B's car contribution (200,000 ج.م)
        $this->command->info('🚗 Adding Partner B car contribution...');

        $asset = FixedAsset::create([
            'name' => 'سيارة - مساهمة من الشريك B',
            'description' => 'Toyota Corolla 2024 - مساهمة رأسمالية',
            'purchase_amount' => 200000,
            'purchase_date' => now(),
            'funding_method' => 'equity',
            'treasury_id' => $treasury->id,
            'partner_id' => $partnerB->id,
            'status' => 'active',
            'useful_life_years' => 5,
            'salvage_value' => 50000,
            'depreciation_method' => 'straight_line',
            'is_contributed_asset' => true,
            'contributing_partner_id' => $partnerB->id,
            'accumulated_depreciation' => 0,
        ]);

        $this->command->info("✓ Car asset created (Value: 200,000 ج.م, Monthly depreciation: " . number_format($asset->calculateMonthlyDepreciation(), 2) . " ج.م)");

        // Record the asset contribution as capital injection
        $capitalService->injectCapital($partnerB, 200000, 'asset', [
            'description' => 'مساهمة بأصل ثابت: ' . $asset->name,
            'reference_type' => FixedAsset::class,
            'reference_id' => $asset->id,
        ]);

        $this->command->info('✓ Capital injection recorded for car contribution');
        $this->command->info('✓ Old period closed, new percentages calculated');

        // Show new percentages
        $partnerA->refresh();
        $partnerB->refresh();
        $this->command->newLine();
        $this->command->info('📊 New equity percentages:');
        $this->command->info("   {$partnerA->name}: " . number_format($partnerA->equity_percentage, 2) . '%');
        $this->command->info("   {$partnerB->name}: " . number_format($partnerB->equity_percentage, 2) . '%');

        $this->command->newLine();
        $this->command->info('✅ Test data created successfully!');
        $this->command->newLine();
        $this->command->info('📍 Next Steps:');
        $this->command->info('1. Go to Filament admin panel');
        $this->command->info('2. Navigate to "إدارة رأس المال" → "فترات رأس المال"');
        $this->command->info('3. You should see Period #1 (status: مفتوحة)');
        $this->command->newLine();
        $this->command->info('🧪 To test capital injection:');
        $this->command->info('   php artisan tinker');
        $this->command->info('   $capitalService = app(\App\Services\CapitalService::class);');
        $this->command->info('   $partnerA = Partner::where("name", "LIKE", "%محمد%")->first();');
        $this->command->info('   $capitalService->injectCapital($partnerA, 50000, "cash");');
        $this->command->newLine();
    }
}
