<?php

use App\Models\InvoicePayment;
use App\Models\Installment;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->treasuryService = app(TreasuryService::class);
    $this->treasury = TestHelpers::createFundedTreasury('10000.0000');
});

test('it creates payment record and treasury transaction', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);

    $payment = $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 5000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
        notes: 'Test payment'
    );

    expect($payment)->toBeInstanceOf(InvoicePayment::class);
    expect((float)$payment->amount)->toBe(5000.0);
    expect($payment->payable_id)->toBe($invoice->id);
    expect($payment->treasury_transaction_id)->not->toBeNull();

    // Check treasury transaction was created
    $transaction = $payment->treasuryTransaction;
    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(5000.0);
    expect($transaction->type)->toBe('collection');
});

test('it applies settlement discount correctly', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);

    // Payment: 9500 cash + 500 discount = 10000 total settled
    $payment = $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 9500.0,
        discount: 500.0,
        treasuryId: $this->treasury->id,
    );

    expect((float)$payment->amount)->toBe(9500.0);
    expect((float)$payment->discount)->toBe(500.0);

    // Treasury should only receive cash (9500), not discount
    $transaction = $payment->treasuryTransaction;
    expect((float)$transaction->amount)->toBe(9500.0);

    // Invoice paid_amount should include discount
    $invoice->refresh();
    expect((float)$invoice->paid_amount)->toBe(10000.0); // 9500 + 500
    expect((float)$invoice->remaining_amount)->toBe(0.0);
});

test('it updates invoice paid amount and remaining amount', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);

    // First payment: 3000
    $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 3000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
    );

    $invoice->refresh();
    expect((float)$invoice->paid_amount)->toBe(3000.0);
    expect((float)$invoice->remaining_amount)->toBe(7000.0);

    // Second payment: 5000
    $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 5000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
    );

    $invoice->refresh();
    expect((float)$invoice->paid_amount)->toBe(8000.0);
    expect((float)$invoice->remaining_amount)->toBe(2000.0);
});

test('it applies payment to installments fifo', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    // Generate installments
    app(\App\Services\InstallmentService::class)->generateInstallmentSchedule($invoice);

    // Payment: 7000 (should cover first 2 installments fully, partial on 3rd)
    // Installments are ~3333.33 each, so 7000 = 3333.33 + 3333.33 + 333.34
    $payment = $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 7000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
    );

    // Check installments
    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();
    expect($installment1->status)->toBe('paid');
    expect((float)$installment1->paid_amount)->toBe((float)$installment1->amount);

    $installment2 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 2)
        ->first();
    expect($installment2->status)->toBe('paid');
    expect((float)$installment2->paid_amount)->toBe((float)$installment2->amount);

    $installment3 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 3)
        ->first();
    expect($installment3->status)->toBe('pending');
    expect((float)$installment3->paid_amount)->toBeGreaterThan(0.0);
    expect((float)$installment3->paid_amount)->toBeLessThan((float)$installment3->amount);
});

test('it updates partner balance after payment', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);

    // Initial balance should be 10000
    $this->treasuryService->updatePartnerBalance($partner->id);
    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(10000.0);

    // Record payment: 5000
    $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 5000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
    );

    // Balance should be updated
    $partner->refresh();
    expect((float)$partner->current_balance)->toBe(5000.0); // 10000 - 5000
});

test('it throws exception when payment on draft invoice', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '10000.0000',
    ]);

    expect(fn () => $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 5000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
    ))->toThrow(Exception::class, 'Cannot record payment on draft invoice');
});

test('it handles overpayment scenario', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);

    // Overpayment should now throw an exception
    expect(fn () => $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 15000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
    ))->toThrow(\Exception::class, 'لا يمكن الدفع أكثر من المبلغ المتبقي');
});

test('it handles zero discount payment', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);

    $payment = $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 5000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
    );

    expect((float)$payment->discount)->toBe(0.0);
    expect((float)$payment->amount)->toBe(5000.0);
});
