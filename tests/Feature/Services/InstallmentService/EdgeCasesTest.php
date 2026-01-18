<?php

use App\Models\Installment;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\InvoicePayment;
use App\Services\InstallmentService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->installmentService = app(InstallmentService::class);
});

test('it handles single month installment plan', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '1000.0000',
        'remaining_amount' => '1000.0000',
        'has_installment_plan' => true,
        'installment_months' => 1,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installments = Installment::where('sales_invoice_id', $invoice->id)->get();
    expect($installments)->toHaveCount(1);
    expect($installments[0]->amount)->toBe('1000.0000');
});

test('it handles very small remaining amounts', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '0.0001',
        'remaining_amount' => '0.0001',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installments = Installment::where('sales_invoice_id', $invoice->id)
        ->orderBy('installment_number')
        ->get();

    expect($installments)->toHaveCount(3);
    expect($installments[0]->amount)->toBe('0.0000');
    expect($installments[1]->amount)->toBe('0.0000');
    expect($installments[2]->amount)->toBe('0.0001');
});

test('it handles far future start dates', function () {
    $partner = Partner::factory()->customer()->create();
    $futureDate = now()->addYears(5)->format('Y-m-d');
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '1000.0000',
        'remaining_amount' => '1000.0000',
        'has_installment_plan' => true,
        'installment_months' => 12,
        'installment_start_date' => $futureDate,
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installments = Installment::where('sales_invoice_id', $invoice->id)
        ->orderBy('due_date')
        ->get();

    expect($installments[0]->due_date->format('Y-m-d'))->toBe($futureDate);
    expect($installments[0]->status)->toBe('pending');
});

test('it handles long notes', function () {
    $partner = Partner::factory()->customer()->create();
    $longNotes = str_repeat('Note ', 100);
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '1000.0000',
        'remaining_amount' => '1000.0000',
        'has_installment_plan' => true,
        'installment_months' => 1,
        'installment_start_date' => now()->format('Y-m-d'),
        'installment_notes' => $longNotes,
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installment = Installment::where('sales_invoice_id', $invoice->id)->first();
    expect($installment->notes)->toBe($longNotes);
});

test('it handles applying payment when no installments exist', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '1000.0000',
        'remaining_amount' => '1000.0000',
        'has_installment_plan' => false,
    ]);

    $payment = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '500.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    // Should not throw exception, just log a warning
    $this->installmentService->applyPaymentToInstallments($invoice, $payment);
    
    expect(true)->toBeTrue();
});

test('it throws exception if installments already generated', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '1000.0000',
        'remaining_amount' => '1000.0000',
        'has_installment_plan' => true,
        'installment_months' => 2,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);
    
    expect(fn() => $this->installmentService->generateInstallmentSchedule($invoice))
        ->toThrow(Exception::class, 'خطة الأقساط موجودة بالفعل');
});

test('it throws exception for zero installment months', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '1000.0000',
        'remaining_amount' => '1000.0000',
        'has_installment_plan' => true,
        'installment_months' => 0,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    expect(fn () => $this->installmentService->generateInstallmentSchedule($invoice))
        ->toThrow(Exception::class, 'عدد الأقساط يجب أن يكون أكبر من الصفر');
});

test('it throws exception for negative remaining amount', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '1000.0000',
        'remaining_amount' => '-10.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    expect(fn () => $this->installmentService->generateInstallmentSchedule($invoice))
        ->toThrow(Exception::class, 'لا يوجد مبلغ متبقي للتقسيط');
});
