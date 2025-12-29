<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Invoice PDF Print Routes
Route::get('/invoices/sales/{invoice}/print', [InvoiceController::class, 'printSalesInvoice'])
    ->name('invoices.sales.print')
    ->middleware('auth');
