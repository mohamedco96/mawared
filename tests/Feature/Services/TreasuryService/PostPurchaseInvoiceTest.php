<?php

use App\Models\Partner;
use App\Models\PurchaseInvoice;
use App\Models\Treasury;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->treasuryService = app(TreasuryService::class);
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
});

test('it creates payment transaction for cash purchase invoice', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '5000.0000',
        'paid_amount' => '5000.0000',
        'remaining_amount' => '0.0000',
    ]);

    $this->treasuryService->postPurchaseInvoice($invoice, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'purchase_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(-5000.0); // Negative for payment
    expect($transaction->type)->toBe('payment');
});

test('it does not create transaction for credit purchase invoice', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    $this->treasuryService->postPurchaseInvoice($invoice, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'purchase_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // Should not create transaction for credit invoice (no cash paid)
    expect($transaction)->toBeNull();
});

test('it updates partner balance after posting', function () {
    $partner = Partner::factory()->supplier()->create([
        'opening_balance' => '0.0000',
    ]);
    
    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    $this->treasuryService->postPurchaseInvoice($invoice, $this->treasury->id);
    $invoice->update(['status' => 'posted']);
    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Supplier balance should be positive (we owe them)
    expect((float)$partner->current_balance)->toBe(5000.0);
});

test('it throws exception when invoice not draft', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'cash',
        'total' => '5000.0000',
        'paid_amount' => '5000.0000',
    ]);

    expect(fn () => $this->treasuryService->postPurchaseInvoice($invoice, $this->treasury->id))
        ->toThrow(Exception::class, 'الفاتورة ليست في حالة مسودة');
});

test('it handles partial payment purchase invoice', function () {
    $partner = Partner::factory()->supplier()->create();
    
    $invoice = PurchaseInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '10000.0000',
        'paid_amount' => '3000.0000', // Partial payment
        'remaining_amount' => '7000.0000',
    ]);

    $this->treasuryService->postPurchaseInvoice($invoice, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'purchase_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // Should only create transaction for paid_amount (3000), not total
    expect((float)$transaction->amount)->toBe(-3000.0);
});
