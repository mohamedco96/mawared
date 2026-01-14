<?php

use App\Models\Installment;
use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\TreasuryTransaction;
use App\Services\InstallmentService;
use App\Services\TreasuryService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->treasuryService = app(TreasuryService::class);
    $this->installmentService = app(InstallmentService::class);
    $this->treasury = TestHelpers::createFundedTreasury('100000.0000');
});

test('it completes payment flow with installments', function () {
    $customer = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    // Post credit invoice
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '9000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '9000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    // Generate installments
    $this->installmentService->generateInstallmentSchedule($invoice);

    $initialBalance = $this->treasuryService->getTreasuryBalance($this->treasury->id);
    $this->treasuryService->updatePartnerBalance($customer->id);
    $customer->refresh();
    $initialPartnerBalance = (float)$customer->current_balance;

    // Record payment
    $payment = $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 4000.0,
        discount: 0.0,
        treasuryId: $this->treasury->id,
        notes: 'First installment payment'
    );

    // Verify treasury transaction created (payment transactions don't have reference_type)
    $transaction = $payment->treasuryTransaction;
    expect($transaction)->not->toBeNull();
    expect((float)$transaction->amount)->toBe(4000.0);
    expect($transaction->type)->toBe('collection');

    // Verify invoice paid_amount updated
    $invoice->refresh();
    expect((float)$invoice->paid_amount)->toBe(4000.0);
    expect((float)$invoice->remaining_amount)->toBe(5000.0);

    // Verify installments updated
    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();
    expect($installment1->status)->toBe('paid');

    // Verify partner balance updated
    $customer->refresh();
    expect((float)$customer->current_balance)->toBe($initialPartnerBalance - 4000.0);
});

test('it handles payment with settlement discount', function () {
    $customer = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $customer->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
    ]);

    $this->treasuryService->updatePartnerBalance($customer->id);
    $customer->refresh();
    $initialPartnerBalance = (float)$customer->current_balance;

    // Payment: 9500 cash + 500 discount
    $payment = $this->treasuryService->recordInvoicePayment(
        $invoice,
        amount: 9500.0,
        discount: 500.0,
        treasuryId: $this->treasury->id,
    );

    // Treasury should only receive cash (9500)
    $transaction = $payment->treasuryTransaction;
    expect((float)$transaction->amount)->toBe(9500.0);

    // Invoice paid_amount should include discount (10000)
    $invoice->refresh();
    expect((float)$invoice->paid_amount)->toBe(10000.0);
    expect((float)$invoice->remaining_amount)->toBe(0.0);

    // Partner balance should be reduced by total settled (10000)
    $customer->refresh();
    expect((float)$customer->current_balance)->toBe($initialPartnerBalance - 10000.0);
});
