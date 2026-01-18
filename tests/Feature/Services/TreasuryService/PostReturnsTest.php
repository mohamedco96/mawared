<?php

use App\Models\Partner;
use App\Models\PurchaseReturn;
use App\Models\SalesReturn;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->treasuryService = app(TreasuryService::class);
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
});

test('it creates refund transaction for cash sales return', function () {
    $partner = Partner::factory()->customer()->create();
    
    $return = SalesReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '2000.0000',
    ]);

    $this->treasuryService->postSalesReturn($return, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(-2000.0); // Negative - money leaves treasury
    expect($transaction->type)->toBe('refund');
});

test('it does not create transaction for credit sales return', function () {
    $partner = Partner::factory()->customer()->create();
    
    $return = SalesReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '2000.0000',
    ]);

    $this->treasuryService->postSalesReturn($return, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'sales_return')
        ->where('reference_id', $return->id)
        ->first();

    // Should not create transaction for credit return
    expect($transaction)->toBeNull();
});

test('it updates partner balance after sales return', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);
    
    $return = SalesReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '2000.0000',
    ]);

    $this->treasuryService->postSalesReturn($return, $this->treasury->id);
    $return->update(['status' => 'posted']);
    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Credit return should reduce customer debt (balance decreases)
    expect((float)$partner->current_balance)->toBe(-2000.0);
});

test('it creates refund transaction for cash purchase return', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $return = PurchaseReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '2000.0000',
    ]);

    $this->treasuryService->postPurchaseReturn($return, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(2000.0); // Positive - money returns to treasury
    expect($transaction->type)->toBe('refund');
});

test('it does not create transaction for credit purchase return', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $return = PurchaseReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '2000.0000',
    ]);

    $this->treasuryService->postPurchaseReturn($return, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->first();

    // Should not create transaction for credit return
    expect($transaction)->toBeNull();
});

test('it updates partner balance after purchase return', function () {
    $partner = Partner::factory()->supplier()->create([
        'opening_balance' => '0.0000',
    ]);
    
    $return = PurchaseReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '2000.0000',
    ]);

    $this->treasuryService->postPurchaseReturn($return, $this->treasury->id);
    $return->update(['status' => 'posted']);
    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Credit return should reduce our debt to supplier (balance becomes less negative)
    expect((float)$partner->current_balance)->toBe(-2000.0);
});

test('it prevents duplicate transactions for purchase return', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $return = PurchaseReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '2000.0000',
    ]);

    // Post first time
    $this->treasuryService->postPurchaseReturn($return, $this->treasury->id);

    // Try to post again (should be idempotent)
    $return->refresh();
    $return->update(['status' => 'draft']); // Reset to draft for testing
    
    $this->treasuryService->postPurchaseReturn($return, $this->treasury->id);

    // Should only have one transaction (idempotency check prevents duplicate)
    $transactions = TreasuryTransaction::where('reference_type', 'purchase_return')
        ->where('reference_id', $return->id)
        ->count();

    expect($transactions)->toBe(1);
});

test('it throws exception when return not draft', function () {
    $partner = Partner::factory()->customer()->create();
    
    $return = SalesReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'cash',
        'total' => '2000.0000',
    ]);

    expect(fn () => $this->treasuryService->postSalesReturn($return, $this->treasury->id))
        ->toThrow(Exception::class, 'المرتجع ليس في حالة مسودة');
});
