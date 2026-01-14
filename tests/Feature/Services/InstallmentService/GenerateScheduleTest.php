<?php

use App\Models\Installment;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Services\InstallmentService;
use Tests\Helpers\TestHelpers;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->installmentService = app(InstallmentService::class);
});

test('it creates correct number of installments', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
        'has_installment_plan' => true,
        'installment_months' => 5,
        'installment_start_date' => now()->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installments = Installment::where('sales_invoice_id', $invoice->id)->get();
    expect($installments)->toHaveCount(5);
});

test('it calculates installment amounts correctly', function () {
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

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installments = Installment::where('sales_invoice_id', $invoice->id)
        ->orderBy('installment_number')
        ->get();

    // Each installment should be approximately 3333.33 (10000 / 3)
    expect(abs((float)$installments[0]->amount - 3333.3333))->toBeLessThan(0.0001);
    expect(abs((float)$installments[1]->amount - 3333.3333))->toBeLessThan(0.0001);
    expect(abs((float)$installments[2]->amount - 3333.3334))->toBeLessThan(0.0001); // Last one gets rounding difference
});

test('it handles rounding difference in last installment', function () {
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

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installments = Installment::where('sales_invoice_id', $invoice->id)
        ->orderBy('installment_number')
        ->get();

    // Sum of all installments should equal remaining_amount
    $total = $installments->sum(fn($i) => (float)$i->amount);
    expect(abs($total - 10000.0))->toBeLessThan(0.0001);
});

test('it throws exception when invoice not posted', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'draft',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'remaining_amount' => '10000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
    ]);

    expect(fn () => $this->installmentService->generateInstallmentSchedule($invoice))
        ->toThrow(Exception::class, 'الفاتورة يجب أن تكون مرحّلة');
});

test('it throws exception when schedule already exists', function () {
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

    // Generate schedule first time
    $this->installmentService->generateInstallmentSchedule($invoice);

    // Try to generate again
    expect(fn () => $this->installmentService->generateInstallmentSchedule($invoice))
        ->toThrow(Exception::class, 'خطة الأقساط موجودة بالفعل');
});

test('it throws exception when no remaining amount', function () {
    $partner = Partner::factory()->customer()->create();
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '10000.0000',
        'remaining_amount' => '0.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
    ]);

    expect(fn () => $this->installmentService->generateInstallmentSchedule($invoice))
        ->toThrow(Exception::class, 'لا يوجد مبلغ متبقي');
});

test('it handles different month lengths', function () {
    $partner = Partner::factory()->customer()->create();
    
    // Start date in February (28/29 days)
    $startDate = now()->setMonth(2)->setDay(1)->format('Y-m-d');
    
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'payment_method' => 'credit',
        'total' => '10000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '10000.0000',
        'has_installment_plan' => true,
        'installment_months' => 3,
        'installment_start_date' => $startDate,
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    $installments = Installment::where('sales_invoice_id', $invoice->id)
        ->orderBy('installment_number')
        ->get();

    expect($installments)->toHaveCount(3);
    
    // Check due dates are correctly spaced
    $firstDueDate = \Carbon\Carbon::parse($installments[0]->due_date);
    $secondDueDate = \Carbon\Carbon::parse($installments[1]->due_date);
    $thirdDueDate = \Carbon\Carbon::parse($installments[2]->due_date);

    expect($secondDueDate->diffInMonths($firstDueDate))->toBe(1);
    expect($thirdDueDate->diffInMonths($secondDueDate))->toBe(1);
});
