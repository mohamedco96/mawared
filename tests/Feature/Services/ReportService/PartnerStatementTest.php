<?php

use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Services\ReportService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->reportService = app(ReportService::class);
});

test('it generates partner statement with correct opening balance', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '1000.0000',
    ]);

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    expect($statement)->toBeArray();
    expect($statement)->toHaveKey('opening_balance');
    expect((float) $statement['opening_balance'])->toBe(1000.0);
});

test('it includes all transactions in date range', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    // Create invoice before date range (should be in opening balance)
    $oldInvoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);
    $oldInvoice->created_at = now()->subMonths(2);
    $oldInvoice->saveQuietly();

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Create invoice in date range
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '3000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '3000.0000',
        'created_at' => now(),
    ]);

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    expect($statement['transactions'])->toHaveCount(1);
    expect($statement['transactions'][0]['type'])->toBe('invoice');
    expect($statement['transactions'][0]['reference'])->toBe($invoice->invoice_number);
});

test('it calculates running balance correctly', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '1000.0000',
    ]);

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Create invoice (yesterday to ensure it comes before payment)
    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);
    $invoice->created_at = now()->subDay();
    $invoice->saveQuietly();

    // Create payment (today)
    $payment = InvoicePayment::create([
        'payable_type' => 'sales_invoice',
        'payable_id' => $invoice->id,
        'amount' => '2000.0000',
        'discount' => '0.0000',
        'payment_date' => now(),
        'partner_id' => $partner->id,
    ]);

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    $transactions = $statement['transactions'];

    // First transaction (invoice): balance = 1000 + 5000 = 6000
    expect((float) $transactions[0]['balance'])->toBe(6000.0);

    // Second transaction (payment): balance = 6000 - 2000 = 4000
    expect((float) $transactions[1]['balance'])->toBe(4000.0);
});

test('it returns correct closing balance', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '1000.0000',
    ]);

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $invoice = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '5000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '5000.0000',
    ]);
    $invoice->created_at = now();
    $invoice->saveQuietly();

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    // Closing balance: 1000 (opening) + 5000 (invoice) = 6000
    expect((float) $statement['closing_balance'])->toBe(6000.0);
});

test('it handles partner with no transactions', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    expect($statement['transactions'])->toHaveCount(0);
    expect((float) $statement['opening_balance'])->toBe(0.0);
    expect((float) $statement['closing_balance'])->toBe(0.0);
});

test('it handles date boundaries correctly', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    $startDate = now()->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    // Invoice on start date
    $invoice1 = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '1000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '1000.0000',
        'created_at' => now()->startOfDay(),
    ]);

    // Invoice on end date
    $invoice2 = SalesInvoice::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '2000.0000',
        'paid_amount' => '0.0000',
        'remaining_amount' => '2000.0000',
        'created_at' => now()->endOfDay(),
    ]);

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    // Both invoices should be included
    expect($statement['transactions'])->toHaveCount(2);
});

test('it includes sales returns in statement', function () {
    $partner = Partner::factory()->customer()->create([
        'opening_balance' => '0.0000',
    ]);

    $startDate = now()->subMonths(1)->format('Y-m-d');
    $endDate = now()->format('Y-m-d');

    $return = SalesReturn::factory()->create([
        'partner_id' => $partner->id,
        'status' => 'posted',
        'total' => '1000.0000',
        'created_at' => now(),
    ]);

    $statement = $this->reportService->getPartnerStatement($partner->id, $startDate, $endDate);

    $transactions = $statement['transactions'];
    expect($transactions)->toHaveCount(1);
    expect($transactions[0]['type'])->toBe('return');
    expect($transactions[0]['reference'])->toBe($return->return_number);
});
