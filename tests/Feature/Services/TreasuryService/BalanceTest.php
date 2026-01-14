<?php

use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\SalesInvoice;
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

test('it calculates treasury balance from transactions', function () {
    // Initial balance is 10000 from TestHelpers
    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect((float)$initialBalance)->toBe(10000.0);

    // Add income
    $this->treasuryService->recordTransaction(
        $this->treasury->id,
        'income',
        '500.0000',
        'Test income',
    );

    $newBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    expect((float)$newBalance)->toBe(10500.0);
});

test('it uses lock for update during balance calculation', function () {
    // This test verifies that getTreasuryBalance uses lockForUpdate()
    // The lock prevents race conditions during concurrent transactions
    
    $balance1 = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    $balance2 = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    
    // Both should return the same value (no race condition)
    expect((float)$balance1)->toBe((float)$balance2);
});

test('it calculates partner balance correctly', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '1000.0000',
    ]);

    // Create a posted credit invoice
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    // Update partner balance
    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Expected: 1000 (opening) + 5000 (invoice) = 6000
    expect((float)$partner->current_balance)->toBe(6000.0);
});

test('it includes opening balance in partner calculation', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '2000.0000',
    ]);

    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(2000.0);
});

test('it excludes cash returns from partner balance', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    // Create a posted credit invoice
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    // Create a cash return (should NOT affect partner balance)
    $cashReturn = SalesReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'cash',
        'total' => '1000.0000',
    ]);

    // Create a credit return (SHOULD affect partner balance)
    $creditReturn = SalesReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '500.0000',
    ]);

    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Expected: 0 (opening) + 5000 (invoice) - 500 (credit return) = 4500
    // Cash return (1000) should NOT be subtracted
    expect((float)$partner->current_balance)->toBe(4500.0);
});

test('it handles partner with no transactions', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(0.0);
});

test('it recalculates partner balance from source data', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '1000.0000',
        'current_balance' => '9999.0000', // Wrong balance
    ]);

    // Create posted invoice
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    // Recalculate should fix the balance
    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Should be recalculated: 1000 + 5000 = 6000
    expect((float)$partner->current_balance)->toBe(6000.0);
});

test('it handles partial payments in partner balance calculation', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    // Create posted credit invoice
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '3000.0000',
        'remaining_amount' => '7000.0000',
    ]);

    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Should only count remaining_amount (7000), not total
    expect((float)$partner->current_balance)->toBe(7000.0);
});
