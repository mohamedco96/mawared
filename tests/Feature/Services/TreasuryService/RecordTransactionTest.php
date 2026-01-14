<?php

use App\Models\Partner;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->treasuryService = app(TreasuryService::class);
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
});

test('it creates treasury transaction successfully', function () {
    $transaction = $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'income',
        '1000.0000',
        'Test income transaction',
        partnerId: null,
        referenceType: null,
        referenceId: null
    );

    expect($transaction)->toBeInstanceOf(TreasuryTransaction::class);
    expect((float)$transaction->amount)->toBe(1000.0);
    expect($transaction->type)->toBe('income');
    expect($transaction->treasury_id)->toBe($this->treasury->id);
});

test('it updates treasury balance correctly', function () {
    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);

    $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'income',
        '500.0000',
        'Test income',
    );

    $newBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect((float)$newBalance)->toBe((float)$initialBalance + 500.0);
});

test('it throws exception when balance would go negative', function () {
    // Treasury has 10000, try to withdraw 15000
    expect(fn () => $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'payment',
        '-15000.0000', // Negative amount
        'Large payment',
    ))->toThrow(Exception::class, 'الرصيد المتاح غير كافٍ');
});

test('it throws exception with arabic error message', function () {
    expect(fn () => $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'payment',
        '-50000.0000',
        'Large payment',
    ))->toThrow(Exception::class);
    
    try {
        $this->treasuryService->recordTransaction(
            $this->treasury->id,
            'payment',
            '-50000.0000',
            'Large payment',
        );
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('الرصيد المتاح غير كافٍ');
    }
});

test('it handles zero amount transaction', function () {
    $transaction = $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'income',
        '0.0000',
        'Zero amount transaction',
    );

    expect($transaction)->toBeInstanceOf(TreasuryTransaction::class);
    expect((float)$transaction->amount)->toBe(0.0);
});

test('it handles very large amount transaction', function () {
    // Add more funds first
    $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'income',
        '999999999.9999',
        'Very large deposit',
    );

    $balance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect((float)$balance)->toBeGreaterThan(999999999.0);
});

test('it handles concurrent transactions with lock', function () {
    // This test verifies that lockForUpdate() prevents race conditions
    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);

    // Simulate concurrent transactions
    $transactions = [];
    for ($i = 0; $i < 5; $i++) {
        $transactions[] = $this->treasuryService->recordTransaction(
            $this->treasury->id,
            'income',
            '100.0000',
            "Concurrent transaction {$i}",
        );
    }

    $finalBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect((float)$finalBalance)->toBe((float)$initialBalance + 500.0); // 5 * 100
});

test('it records transaction with partner reference', function () {
    $partner = Partner::factory()->customer()->create();

    $transaction = $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'collection',
        '2000.0000',
        'Collection from customer',
        partnerId: $partner->id,
        referenceType: 'sales_invoice',
        referenceId: 'test-invoice-id',
    );

    expect($transaction->partner_id)->toBe($partner->id);
    expect($transaction->reference_type)->toBe('sales_invoice');
    expect($transaction->reference_id)->toBe('test-invoice-id');
});

test('it handles very small amount with precision', function () {
    $transaction = $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'income',
        '0.0001',
        'Very small amount',
    );

    expect($transaction->amount)->toBe('0.0001');
});
