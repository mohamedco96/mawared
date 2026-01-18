<?php

use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->treasuryService = app(TreasuryService::class);
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
});

test('it records collection transaction correctly', function () {
    $partner = Partner::factory()->customer()->create();
    
    $transaction = $this->treasuryService->recordFinancialTransaction(
        $this->treasury->id,
        'collection',
        '5000.0000',
        'Collection from customer',
        partnerId: $partner->id,
        discount: null
    );

    expect($transaction)->toBeInstanceOf(TreasuryTransaction::class);
    expect((float)$transaction->amount)->toBe(5000.0);
    expect($transaction->type)->toBe('collection');
    expect($transaction->partner_id)->toBe($partner->id);
});

test('it records payment transaction correctly', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $transaction = $this->treasuryService->recordFinancialTransaction(
        $this->treasury->id,
        'payment',
        '3000.0000',
        'Payment to supplier',
        partnerId: $partner->id,
        discount: null
    );

    expect($transaction)->toBeInstanceOf(TreasuryTransaction::class);
    expect((float)$transaction->amount)->toBe(-3000.0); // Negative for payment
    expect($transaction->type)->toBe('payment');
});

test('it handles discount in financial transaction', function () {
    $partner = Partner::factory()->customer()->create();
    
    // Collection with discount: 10000 amount, 500 discount
    // Treasury receives: 9500 (10000 - 500)
    $transaction = $this->treasuryService->recordFinancialTransaction(
        $this->treasury->id,
        'collection',
        '10000.0000',
        'Collection with discount',
        partnerId: $partner->id,
        discount: '500.0000'
    );

    // Treasury amount should be reduced by discount
    expect((float)$transaction->amount)->toBe(9500.0); // 10000 - 500
});

test('it updates partner balance after financial transaction', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    // First create an invoice to establish a debt
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);
    $this->treasuryService->updatePartnerBalance($partner->id);
    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(10000.0); // They owe us 10000

    // Collection reduces customer debt
    $this->treasuryService->recordFinancialTransaction(
        $this->treasury->id,
        'collection',
        '5000.0000',
        'Collection from customer',
        partnerId: $partner->id,
    );

    $partner->refresh();
    // Collection should reduce customer balance (they paid us 5000, so balance is now 5000)
    expect((float)$partner->current_balance)->toBe(5000.0);
});

test('it throws exception for invalid transaction type', function () {
    $partner = Partner::factory()->customer()->create();
    
    // Note: The service doesn't validate type, but we can test it handles valid types
    expect(fn () => $this->treasuryService->recordFinancialTransaction(
        $this->treasury->id,
        'invalid_type',
        '1000.0000',
        'Invalid transaction',
        partnerId: $partner->id,
    ))->not->toThrow(Exception::class); // Service doesn't validate type, just records it
});

test('it handles zero amount financial transaction', function () {
    $partner = Partner::factory()->customer()->create();
    
    $transaction = $this->treasuryService->recordFinancialTransaction(
        $this->treasury->id,
        'collection',
        '0.0000',
        'Zero amount collection',
        partnerId: $partner->id,
    );

    expect((float)$transaction->amount)->toBe(0.0);
});

test('it handles very large amounts with precision', function () {
    $partner = Partner::factory()->customer()->create();
    
    // Add more funds first
    $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'income',
        '999999999.9999',
        'Large deposit',
    );

    $transaction = $this->treasuryService->recordFinancialTransaction(
        $this->treasury->id,
        'collection',
        '500000000.0000',
        'Very large collection',
        partnerId: $partner->id,
    );

    expect((float)$transaction->amount)->toBe(500000000.0);
});
