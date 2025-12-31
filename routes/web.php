<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PublicQuotationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Invoice PDF Print Routes
Route::get('/invoices/sales/{invoice}/print', [InvoiceController::class, 'printSalesInvoice'])
    ->name('invoices.sales.print')
    ->middleware('auth');

// Public Quotation Routes (No Authentication Required)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/q/{token}', [PublicQuotationController::class, 'show'])
        ->name('quotations.public');

    Route::get('/q/{token}/pdf', [PublicQuotationController::class, 'downloadPdf'])
        ->name('quotations.public.pdf')
        ->middleware('throttle:10,1'); // Stricter limit for PDF downloads
});
