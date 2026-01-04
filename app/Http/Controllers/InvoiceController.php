<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Settings\CompanySettings;
use App\Settings\PrintSettings;

class InvoiceController extends Controller
{
    public function printSalesInvoice(SalesInvoice $invoice)
    {
        // Critical check: Only allow print for posted invoices
        if (!$invoice->isPosted()) {
            abort(403, 'لا يمكن طباعة الفواتير غير المعتمدة');
        }

        // Load all necessary relationships
        $invoice->load([
            'partner',
            'warehouse',
            'items.product.smallUnit',
            'items.product.largeUnit',
            'payments'
        ]);

        // Get settings
        $companySettings = app(CompanySettings::class);
        $printSettings = app(PrintSettings::class);

        // Get format from query parameter (default: a4)
        $format = request()->query('format', 'a4');
        if (!in_array($format, ['a4', 'thermal'])) {
            $format = 'a4';
        }

        // Prepare data for view (NO Arabic processing needed!)
        $data = [
            'invoice' => $invoice,
            'companySettings' => $companySettings,
            'format' => $format,
            'autoPrint' => $printSettings->auto_print_enabled,
        ];

        // Return simple view (browser handles everything)
        return view('invoices.print', $data);
    }
}
