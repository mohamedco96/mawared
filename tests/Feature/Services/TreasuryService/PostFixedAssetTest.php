<?php

use App\Models\FixedAsset;
use App\Models\Partner;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->treasury = Treasury::create([
        'name' => 'Main Treasury',
        'type' => 'cash',
    ]);

    // Seed initial balance for testing
    TreasuryTransaction::create([
        'treasury_id' => $this->treasury->id,
        'type' => 'income',
        'amount' => 10000,
        'description' => 'Initial balance',
    ]);

    $this->treasuryService = app(TreasuryService::class);
});

test('fixed asset with cash funding deducts from treasury', function () {
    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);

    $asset = FixedAsset::create([
        'name' => 'Office Furniture',
        'description' => 'Desk and chairs',
        'purchase_amount' => 5000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'cash',
        'status' => 'draft',
        'created_by' => null,
    ]);

    expect($asset->isDraft())->toBeTrue();

    $this->treasuryService->postFixedAssetPurchase($asset);

    $asset->refresh();

    expect($asset->isPosted())->toBeTrue();
    expect($asset->status)->toBe('active');

    // Check treasury balance decreased
    $newBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect(floatval($newBalance))->toBe(floatval(bcsub($initialBalance, '5000', 4)));

    // Check treasury transaction was created
    $transaction = TreasuryTransaction::where('reference_type', 'fixed_asset')
        ->where('reference_id', $asset->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->type)->toBe('expense');
    expect($transaction->amount)->toBe('-5000.0000');
});

test('fixed asset with payable funding creates liability', function () {
    $supplier = Partner::create([
        'name' => 'Equipment Supplier',
        'type' => 'supplier',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);

    $asset = FixedAsset::create([
        'name' => 'Company Vehicle',
        'description' => 'Delivery truck',
        'purchase_amount' => 8000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'payable',
        'supplier_id' => $supplier->id,
        'status' => 'draft',
        'created_by' => null,
    ]);

    $this->treasuryService->postFixedAssetPurchase($asset);

    $asset->refresh();
    $supplier->refresh();

    expect($asset->isPosted())->toBeTrue();

    // Treasury balance should NOT change (no cash movement)
    $newBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect($newBalance)->toBe($initialBalance);

    // Supplier balance should increase (we owe them)
    expect($supplier->current_balance)->toBe('8000.0000');

    // No treasury transaction should be created for payable
    $transaction = TreasuryTransaction::where('reference_type', 'fixed_asset')
        ->where('reference_id', $asset->id)
        ->first();

    expect($transaction)->toBeNull();
});

test('fixed asset with payable funding creates supplier if only name provided', function () {
    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);

    $asset = FixedAsset::create([
        'name' => 'Office Equipment',
        'description' => 'Printer and scanner',
        'purchase_amount' => 3000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'payable',
        'supplier_name' => 'New Tech Supplier',
        'status' => 'draft',
        'created_by' => null,
    ]);

    expect(Partner::where('name', 'New Tech Supplier')->exists())->toBeFalse();

    $this->treasuryService->postFixedAssetPurchase($asset);

    $asset->refresh();

    expect($asset->isPosted())->toBeTrue();

    // Supplier should be created
    $supplier = Partner::where('name', 'New Tech Supplier')->first();
    expect($supplier)->not->toBeNull();
    expect($supplier->type)->toBe('supplier');
    expect($supplier->current_balance)->toBe('3000.0000');

    // Asset should be linked to supplier
    expect($asset->supplier_id)->toBe($supplier->id);
});

test('fixed asset with equity funding increases partner equity', function () {
    $shareholder = Partner::create([
        'name' => 'John Doe',
        'type' => 'shareholder',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);

    $asset = FixedAsset::create([
        'name' => 'Manufacturing Equipment',
        'description' => 'Production machinery',
        'purchase_amount' => 15000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'equity',
        'partner_id' => $shareholder->id,
        'status' => 'draft',
        'created_by' => null,
    ]);

    $this->treasuryService->postFixedAssetPurchase($asset);

    $asset->refresh();
    $shareholder->refresh();

    expect($asset->isPosted())->toBeTrue();

    // Treasury balance should NOT change (no cash movement)
    $newBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect($newBalance)->toBe($initialBalance);

    // Partner equity should increase
    expect($shareholder->current_balance)->toBe('15000.0000');

    // No treasury transaction should be created for equity (no cash movement)
    $transaction = TreasuryTransaction::where('reference_type', 'fixed_asset')
        ->where('reference_id', $asset->id)
        ->first();

    expect($transaction)->toBeNull();
});

test('fixed asset with equity funding requires partner_id', function () {
    $asset = FixedAsset::create([
        'name' => 'Equipment',
        'purchase_amount' => 5000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'equity',
        'partner_id' => null,
        'status' => 'draft',
        'created_by' => null,
    ]);

    expect(fn () => $this->treasuryService->postFixedAssetPurchase($asset))
        ->toThrow(\Exception::class, 'يجب تحديد الشريك عند اختيار طريقة التمويل: مساهمة رأسمالية');
});

test('cannot post fixed asset that is not draft', function () {
    $asset = FixedAsset::create([
        'name' => 'Equipment',
        'purchase_amount' => 5000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'cash',
        'status' => 'active',
        'created_by' => null,
    ]);

    expect(fn () => $this->treasuryService->postFixedAssetPurchase($asset))
        ->toThrow(\Exception::class, 'الأصل الثابت ليس في حالة مسودة');
});

test('fixed asset with cash funding fails if insufficient treasury balance', function () {
    $asset = FixedAsset::create([
        'name' => 'Expensive Equipment',
        'purchase_amount' => 50000, // More than treasury balance
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'cash',
        'status' => 'draft',
        'created_by' => null,
    ]);

    expect(fn () => $this->treasuryService->postFixedAssetPurchase($asset))
        ->toThrow(\Exception::class, 'لا يمكن إتمام العملية: الرصيد المتاح غير كافٍ في الخزينة');

    $asset->refresh();
    expect($asset->isDraft())->toBeTrue();
});

test('balance sheet is balanced after fixed asset purchase with cash', function () {
    $asset = FixedAsset::create([
        'name' => 'Office Equipment',
        'purchase_amount' => 1000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'cash',
        'status' => 'draft',
        'created_by' => null,
    ]);

    $this->treasuryService->postFixedAssetPurchase($asset);

    // Assets = Fixed Assets + Treasury Balance
    $treasuryBalance = floatval($this->treasuryService->getTreasuryBalance($this->treasury->id));
    $fixedAssetsValue = floatval(FixedAsset::where('status', 'active')->sum('purchase_amount'));
    $totalAssets = $treasuryBalance + $fixedAssetsValue;

    // For cash purchase: Asset increases, Cash decreases => Net effect = 0
    // Total Assets should remain the same as initial balance
    expect($totalAssets)->toBe(10000.0);
});

test('balance sheet is balanced after fixed asset purchase with payable', function () {
    $supplier = Partner::create([
        'name' => 'Equipment Supplier',
        'type' => 'supplier',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $asset = FixedAsset::create([
        'name' => 'Office Equipment',
        'purchase_amount' => 2000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'payable',
        'supplier_id' => $supplier->id,
        'status' => 'draft',
        'created_by' => null,
    ]);

    $this->treasuryService->postFixedAssetPurchase($asset);

    $supplier->refresh();

    // Assets = Fixed Assets + Treasury Balance
    $treasuryBalance = floatval($this->treasuryService->getTreasuryBalance($this->treasury->id));
    $fixedAssetsValue = floatval(FixedAsset::where('status', 'active')->sum('purchase_amount'));
    $totalAssets = $treasuryBalance + $fixedAssetsValue;

    // Liabilities = Supplier Balance
    $totalLiabilities = floatval($supplier->current_balance);

    // For payable purchase: Assets increase, Liabilities increase => Balanced
    // Assets should equal initial balance + fixed assets value
    expect($totalAssets)->toBe(12000.0);
    expect($totalLiabilities)->toBe(2000.0);

    // Assets = Liabilities + Equity (where Equity = initial treasury balance)
    $equity = 10000.0; // Initial balance
    expect($totalAssets)->toBe($totalLiabilities + $equity);
});

test('balance sheet is balanced after fixed asset purchase with equity', function () {
    $shareholder = Partner::create([
        'name' => 'John Doe',
        'type' => 'shareholder',
        'opening_balance' => 0,
        'current_balance' => 0,
    ]);

    $asset = FixedAsset::create([
        'name' => 'Office Equipment',
        'purchase_amount' => 3000,
        'treasury_id' => $this->treasury->id,
        'purchase_date' => now(),
        'funding_method' => 'equity',
        'partner_id' => $shareholder->id,
        'status' => 'draft',
        'created_by' => null,
    ]);

    $this->treasuryService->postFixedAssetPurchase($asset);

    $shareholder->refresh();

    // Assets = Fixed Assets + Treasury Balance
    $treasuryBalance = floatval($this->treasuryService->getTreasuryBalance($this->treasury->id));
    $fixedAssetsValue = floatval(FixedAsset::where('status', 'active')->sum('purchase_amount'));
    $totalAssets = $treasuryBalance + $fixedAssetsValue;

    // Equity = Shareholder Balance
    $totalEquity = floatval($shareholder->current_balance);

    // For equity purchase: Assets increase, Equity increases => Balanced
    expect($totalAssets)->toBe(13000.0);
    expect($totalEquity)->toBe(3000.0);

    // Assets = Liabilities + Equity (where Liabilities = 0, Equity = initial + contribution)
    $initialEquity = 10000.0; // Initial treasury balance
    expect($totalAssets)->toBe($initialEquity + $totalEquity);
});
