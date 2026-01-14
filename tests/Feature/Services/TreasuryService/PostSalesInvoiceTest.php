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

test('it creates collection transaction for cash invoice', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '5000.0000',
        'paid_amount' => '5000.0000',
        'remaining_amount' => '0.0000',
    ]);

    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(5000.0);
    expect($transaction->type)->toBe('collection');
});

test('it does not create transaction for credit invoice', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // Should not create transaction for credit invoice (no cash received)
    expect($transaction)->toBeNull();
});

test('it updates partner balance after posting', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);
    $invoice->update(['status' => 'posted']);
    $this->treasuryService->updatePartnerBalance($partner->id);

    $partner->refresh();
    // Balance should be updated to reflect the credit invoice
    expect((float)$partner->current_balance)->toBe(5000.0);
});

test('it throws exception when invoice not draft', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'cash',
        'total' => '5000.0000',
        'paid_amount' => '5000.0000',
    ]);

    expect(fn () => $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id))
        ->toThrow(Exception::class, 'الفاتورة ليست في حالة مسودة');
});

test('it handles partial payment invoice', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'cash',
        'total' => '10000.0000',
        'paid_amount' => '3000.0000', // Partial payment
        'remaining_amount' => '7000.0000',
    ]);

    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // Should only create transaction for paid_amount (3000), not total
    expect((float)$transaction->amount)->toBe(3000.0);
});

test('it handles zero paid amount invoice', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);

    $this->treasuryService->postSalesInvoice($invoice, $this->treasury->id);

    $transaction = TreasuryTransaction::where('reference_type', 'sales_invoice')
        ->where('reference_id', $invoice->id)
        ->first();

    // Should not create transaction when paid_amount is 0
    expect($transaction)->toBeNull();
});
