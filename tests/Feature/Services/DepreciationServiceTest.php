<?php

namespace Tests\Feature\Services;

use App\Enums\ExpenseCategoryType;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FixedAsset;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\DepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepreciationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DepreciationService $depreciationService;

    protected Treasury $treasury;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depreciationService = new DepreciationService;

        // Create a treasury for reference (though depreciation doesn't use it)
        $this->treasury = Treasury::factory()->create([
            'type' => 'cash',
            'name' => 'Main Treasury',
        ]);

        // Seed treasury
        TreasuryTransaction::create([
            'treasury_id' => $this->treasury->id,
            'type' => 'income',
            'amount' => '1000000.00',
            'description' => 'Seed',
        ]);
    }

    public function test_calculates_monthly_depreciation_correctly(): void
    {
        // ARRANGE
        // Cost: 12000, Life: 1 year (12 months), Salvage: 0
        // Monthly = 12000 / 12 = 1000
        $asset = FixedAsset::factory()->create([
            'purchase_amount' => 12000,
            'useful_life_years' => 1,
            'salvage_value' => 0,
            'status' => 'active',
        ]);

        // ACT
        $monthlyAmount = $this->depreciationService->calculateAssetDepreciation($asset);

        // ASSERT
        $this->assertEquals('1000.0000', $monthlyAmount);
    }

    public function test_calculates_monthly_depreciation_with_salvage_value(): void
    {
        // ARRANGE
        // Cost: 13000, Salvage: 1000, Life: 1 year
        // Depreciable = 12000. Monthly = 1000.
        $asset = FixedAsset::factory()->create([
            'purchase_amount' => 13000,
            'salvage_value' => 1000,
            'useful_life_years' => 1,
            'status' => 'active',
        ]);

        // ACT
        $monthlyAmount = $this->depreciationService->calculateAssetDepreciation($asset);

        // ASSERT
        $this->assertEquals('1000.0000', $monthlyAmount);
    }

    public function test_returns_zero_depreciation_if_no_useful_life(): void
    {
        // ARRANGE
        $asset = FixedAsset::factory()->create([
            'purchase_amount' => 10000,
            'useful_life_years' => 0,
            'status' => 'active',
        ]);

        // ACT
        $monthlyAmount = $this->depreciationService->calculateAssetDepreciation($asset);

        // ASSERT
        $this->assertEquals('0', $monthlyAmount);
    }

    public function test_process_monthly_depreciation_records_expense_and_updates_asset(): void
    {
        // ARRANGE
        $asset = FixedAsset::factory()->create([
            'name' => 'Test Laptop',
            'purchase_amount' => 12000,
            'useful_life_years' => 1,
            'salvage_value' => 0,
            'accumulated_depreciation' => 0,
            'status' => 'active',
            'treasury_id' => $this->treasury->id,
            'last_depreciation_date' => null,
        ]);

        $month = Carbon::create(2024, 1, 15); // Jan 2024

        // ACT
        $results = $this->depreciationService->processMonthlyDepreciation($month);

        // ASSERT
        $this->assertEquals(1, $results['processed']);
        $this->assertEmpty($results['errors']);

        // Check Asset Update
        $asset->refresh();
        $this->assertEquals('1000.0000', $asset->accumulated_depreciation);
        $this->assertTrue($asset->last_depreciation_date->eq($month->startOfMonth()));

        // Check Expense Record (NOT Treasury Transaction - depreciation is NON-CASH)
        $this->assertDatabaseHas('expenses', [
            'fixed_asset_id' => $asset->id,
            'amount' => '1000.0000',
            'is_non_cash' => true,
            'treasury_id' => null, // Non-cash expense has no treasury
        ]);

        // Verify NO treasury transaction was created for depreciation
        $this->assertDatabaseMissing('treasury_transactions', [
            'type' => 'depreciation_expense',
            'reference_id' => $asset->id,
        ]);
    }

    public function test_skips_assets_already_depreciated_for_the_month(): void
    {
        // ARRANGE
        $month = Carbon::create(2024, 1, 15);

        $asset = FixedAsset::factory()->create([
            'purchase_amount' => 12000,
            'useful_life_years' => 1,
            'status' => 'active',
            'last_depreciation_date' => $month->copy()->startOfMonth(), // Already processed for Jan
        ]);

        // ACT
        $results = $this->depreciationService->processMonthlyDepreciation($month);

        // ASSERT
        // Should not find the asset in the query
        $this->assertEquals(0, $results['processed']);
    }

    public function test_skips_fully_depreciated_assets(): void
    {
        // ARRANGE
        $asset = FixedAsset::factory()->create([
            'purchase_amount' => 12000,
            'useful_life_years' => 1,
            'accumulated_depreciation' => 12000, // Fully depreciated
            'status' => 'active',
            'last_depreciation_date' => null,
        ]);

        // ACT
        $results = $this->depreciationService->processMonthlyDepreciation(now());

        // ASSERT
        $this->assertEquals(0, $results['processed']);
        $this->assertEquals(1, $results['skipped']);
    }

    public function test_caps_depreciation_at_remaining_value(): void
    {
        // ARRANGE
        // Cost 12000. Accumulated 11500. Remaining 500.
        // Monthly calculation is 1000.
        // Should only depreciate 500.
        $asset = FixedAsset::factory()->create([
            'purchase_amount' => 12000,
            'useful_life_years' => 1, // Monthly = 1000
            'salvage_value' => 0,
            'accumulated_depreciation' => 11500,
            'status' => 'active',
            'treasury_id' => $this->treasury->id,
        ]);

        $month = Carbon::now();

        // ACT
        $this->depreciationService->processMonthlyDepreciation($month);

        // ASSERT
        $asset->refresh();
        // 11500 + 500 = 12000
        $this->assertEquals('12000.0000', $asset->accumulated_depreciation);

        // Expense should be 500 (capped at remaining depreciable amount)
        $this->assertDatabaseHas('expenses', [
            'fixed_asset_id' => $asset->id,
            'amount' => '500.0000',
            'is_non_cash' => true,
        ]);
    }

    public function test_idempotency_prevents_duplicate_depreciation(): void
    {
        // ARRANGE
        $asset = FixedAsset::factory()->create([
            'name' => 'Test Asset',
            'purchase_amount' => 12000,
            'useful_life_years' => 1,
            'salvage_value' => 0,
            'accumulated_depreciation' => 0,
            'status' => 'active',
            'treasury_id' => $this->treasury->id,
            'last_depreciation_date' => null,
        ]);

        $month = Carbon::create(2024, 1, 15);

        // ACT - Process twice for same period
        $results1 = $this->depreciationService->processMonthlyDepreciation($month);
        $results2 = $this->depreciationService->processMonthlyDepreciation($month);

        // ASSERT
        $this->assertEquals(1, $results1['processed']);
        // Second call should skip due to last_depreciation_date check
        $this->assertEquals(0, $results2['processed']);

        // Only one expense record should exist
        $this->assertEquals(1, Expense::where('fixed_asset_id', $asset->id)->count());
    }

    public function test_depreciation_creates_correct_expense_category(): void
    {
        // ARRANGE
        $asset = FixedAsset::factory()->create([
            'name' => 'Test Asset',
            'purchase_amount' => 12000,
            'useful_life_years' => 1,
            'salvage_value' => 0,
            'accumulated_depreciation' => 0,
            'status' => 'active',
            'treasury_id' => $this->treasury->id,
        ]);

        // ACT
        $this->depreciationService->processMonthlyDepreciation(Carbon::now());

        // ASSERT
        $expense = Expense::where('fixed_asset_id', $asset->id)->first();
        $this->assertNotNull($expense);
        $this->assertNotNull($expense->expense_category_id);

        $category = ExpenseCategory::find($expense->expense_category_id);
        $this->assertEquals(ExpenseCategoryType::DEPRECIATION->value, $category->getRawOriginal('type'));
    }
}
