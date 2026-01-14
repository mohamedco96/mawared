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

test('it updates overdue installments status', function () {
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
        'installment_start_date' => now()->subMonths(2)->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    // Manually set first installment due date to past (bypass model protection)
    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();
    \DB::table('installments')->where('id', $installment1->id)->update(['due_date' => now()->subDays(10)]);
    $installment1->refresh();

    // Update overdue installments
    $updated = $this->installmentService->updateOverdueInstallments();

    $installment1->refresh();
    expect($installment1->status)->toBe('overdue');
    expect($updated)->toBeGreaterThan(0);
});

test('it does not update paid installments', function () {
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
        'installment_start_date' => now()->subMonths(2)->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    // Mark first installment as paid
    $installment1 = Installment::where('sales_invoice_id', $invoice->id)
        ->where('installment_number', 1)
        ->first();
    \DB::table('installments')->where('id', $installment1->id)->update([
        'status' => 'paid',
        'paid_amount' => $installment1->amount,
        'due_date' => now()->subDays(10), // Past due date
    ]);
    $installment1->refresh();

    $this->installmentService->updateOverdueInstallments();

    $installment1->refresh();
    // Should remain 'paid', not changed to 'overdue'
    expect($installment1->status)->toBe('paid');
});

test('it only updates pending installments', function () {
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
        'installment_start_date' => now()->subMonths(2)->format('Y-m-d'),
    ]);

    $this->installmentService->generateInstallmentSchedule($invoice);

    // Set all installments to past due dates (bypass model protection)
    $installments = Installment::where('sales_invoice_id', $invoice->id)->get();
    foreach ($installments as $installment) {
        \DB::table('installments')->where('id', $installment->id)->update(['due_date' => now()->subDays(10)]);
    }
    $installments->each->refresh();

    $updated = $this->installmentService->updateOverdueInstallments();

    // All pending installments should be updated
    $overdueCount = Installment::where('sales_invoice_id', $invoice->id)
        ->where('status', 'overdue')
        ->count();

    expect($overdueCount)->toBe($installments->count());
    expect($updated)->toBe($installments->count());
});
