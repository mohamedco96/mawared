<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PublicQuotationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ShowroomController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Invoice PDF Print Routes
Route::get('/invoices/sales/{invoice}/print', [InvoiceController::class, 'printSalesInvoice'])
    ->name('invoices.sales.print')
    ->middleware('auth');

// Report PDF Print Routes
Route::middleware('auth')->group(function () {
    Route::get('/reports/partner-statement/print', [ReportController::class, 'printPartnerStatement'])
        ->name('reports.partner-statement.print');

    Route::get('/reports/stock-card/print', [ReportController::class, 'printStockCard'])
        ->name('reports.stock-card.print');

    Route::get('/reports/low-stock/print', [ReportController::class, 'lowStockPrint'])
        ->name('reports.low-stock.print');
});

// Public Quotation Routes (No Authentication Required)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/q/{token}', [PublicQuotationController::class, 'show'])
        ->name('quotations.public');

    Route::get('/q/{token}/pdf', [PublicQuotationController::class, 'downloadPdf'])
        ->name('quotations.public.pdf')
        ->middleware('throttle:10,1'); // Stricter limit for PDF downloads
});

// Public Showroom Routes (No Authentication Required)
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/showroom/{mode}', [ShowroomController::class, 'show'])
        ->name('showroom.show')
        ->where('mode', 'retail|wholesale');

    Route::get('/showroom/qr/{mode}', [ShowroomController::class, 'downloadQr'])
        ->name('showroom.qr.download')
        ->where('mode', 'retail|wholesale')
        ->middleware('throttle:10,1'); // Stricter limit for downloads
});

// Convenience routes for direct access
Route::get('/showroom/retail', fn () => app(ShowroomController::class)->show('retail'))
    ->name('showroom.retail')
    ->middleware('throttle:60,1');

Route::get('/showroom/wholesale', fn () => app(ShowroomController::class)->show('wholesale'))
    ->name('showroom.wholesale')
    ->middleware('throttle:60,1');
