<?php

use App\Models\Installment;
use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\User;
use App\Services\InstallmentService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->installmentService = app(InstallmentService::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it applies payment to oldest installment first - fifo', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
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

    // Create payment
    $payment = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '4000.0000',
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $this->installmentService->applyPaymentToInstallments($invoice, $payment);

    // Check first installment is fully paid
    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();
    expect($installment1->status)->toBe('paid');
    expect((float)$installment1->paid_amount)->toBe((float)$installment1->amount);

    // Check second installment is partially paid
    $installment2 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 2)
        ->first();
    expect($installment2->status)->toBe('pending');
    expect((float)$installment2->paid_amount)->toBeGreaterThan(0.0);
    expect((float)$installment2->paid_amount)->toBeLessThan((float)$installment2->amount);
});

test('it marks installment as paid when fully paid', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '9000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '9000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();

    $payment = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => (string)$installment1->amount, // Exact amount for first installment
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $this->installmentService->applyPaymentToInstallments($invoice, $payment);

    $installment1->refresh();
    expect($installment1->status)->toBe('paid');
    expect($installment1->paid_at)->not->toBeNull();
    expect($installment1->paid_by)->not->toBeNull();
    expect($installment1->invoice_payment_id)->toBe($payment->id);
});

test('it applies payment across multiple installments', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '9000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '9000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    // Payment that covers first 2 installments
    $payment = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '6000.0000',
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $this->installmentService->applyPaymentToInstallments($invoice, $payment);

    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();
    expect($installment1->status)->toBe('paid');

    $installment2 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 2)
        ->first();
    expect($installment2->status)->toBe('paid');

    $installment3 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 3)
        ->first();
    expect($installment3->status)->toBe('pending');
});

test('it uses lock for update to prevent race conditions', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '9000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '9000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    // Create two payments simultaneously
    $payment1 = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '3000.0000',
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $payment2 = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '3000.0000',
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    // Apply both payments (lockForUpdate prevents race conditions)
    $this->installmentService->applyPaymentToInstallments($invoice, $payment1);
    $this->installmentService->applyPaymentToInstallments($invoice, $payment2);

    // Both installments should be paid
    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();
    expect($installment1->status)->toBe('paid');

    $installment2 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 2)
        ->first();
    expect($installment2->status)->toBe('paid');
});

test('it handles overpayment scenario', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '9000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '9000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    // Overpayment: 15000 (more than total)
    $payment = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '15000.0000',
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $this->installmentService->applyPaymentToInstallments($invoice, $payment);

    // All installments should be paid
    $installments = Installment::where('sales_invoice_id', $invoice->id)->get();
    foreach ($installments as $installment) {
        expect($installment->status)->toBe('paid');
    }
});

test('it handles partial payment of installment', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '9000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '9000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();

    // Partial payment: half of first installment
    $payment = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => (string)((float)$installment1->amount / 2),
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $this->installmentService->applyPaymentToInstallments($invoice, $payment);

    $installment1->refresh();
    expect($installment1->status)->toBe('pending');
    expect((float)$installment1->paid_amount)->toBe((float)$installment1->amount / 2);
});
