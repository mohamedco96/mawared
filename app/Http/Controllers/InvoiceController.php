<?php

namespace App\Http\Controllers;

use App\Models\SalesInvoice;
use App\Settings\CompanySettings;
use Arphp\Glyphs;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoiceController extends Controller
{
    public function printSalesInvoice(SalesInvoice $invoice)
    {
        // Critical check: Only allow PDF generation for posted invoices
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

        // Get company settings
        $companySettings = app(CompanySettings::class);

        // Initialize Arabic text processor for proper glyph rendering
        $arabic = new Glyphs();

        // Helper function to process Arabic text
        $processArabic = function($text) use ($arabic) {
            return $text ? $arabic->utf8Glyphs($text, 1000, false) : '';
        };

        // Process all Arabic text fields for proper rendering in PDF
        // Company Information
        $processedCompanyName = $processArabic($companySettings->company_name);
        $processedCompanyAddress = $processArabic($companySettings->company_address);
        $processedCompanyEmail = $processArabic($companySettings->company_email);

        // Partner (Customer) Information
        $processedPartnerName = $processArabic($invoice->partner->name);
        $processedPartnerPhone = $processArabic($invoice->partner->phone ?? 'غير متوفر');
        $processedPartnerAddress = $processArabic($invoice->partner->address ?? 'غير متوفر');

        // Warehouse Information
        $processedWarehouseName = $processArabic($invoice->warehouse->name);
        $processedWarehouseAddress = $processArabic($invoice->warehouse->address ?? 'غير متوفر');

        // Process invoice items - product names and unit names
        $processedItems = $invoice->items->map(function ($item) use ($processArabic) {
            return [
                'original' => $item,
                'product_name' => $processArabic($item->product->name),
                'small_unit_name' => $item->product->smallUnit ? $processArabic($item->product->smallUnit->name) : $processArabic('قطعة'),
                'large_unit_name' => $item->product->largeUnit ? $processArabic($item->product->largeUnit->name) : $processArabic('كرتونة'),
            ];
        });

        // Process invoice notes if exists
        $processedNotes = $processArabic($invoice->notes);

        // Process static Arabic labels for the template
        $labels = [
            'invoice_title' => $processArabic('فاتورة مبيعات / SALES INVOICE'),
            'invoice_number' => $processArabic('رقم الفاتورة:'),
            'date' => $processArabic('التاريخ:'),
            'customer_name' => $processArabic('اسم العميل:'),
            'phone' => $processArabic('الهاتف:'),
            'warehouse' => $processArabic('المخزن:'),
            'warehouse_address' => $processArabic('عنوان المخزن:'),
            'email' => $processArabic('البريد الإلكتروني:'),
            'product' => $processArabic('المنتج'),
            'unit' => $processArabic('الوحدة'),
            'quantity' => $processArabic('الكمية'),
            'price' => $processArabic('السعر'),
            'discount' => $processArabic('الخصم'),
            'total' => $processArabic('الإجمالي'),
            'payment_history' => $processArabic('سجل الدفعات اللاحقة:'),
            'subtotal' => $processArabic('المجموع الفرعي:'),
            'total_discount' => $processArabic('الخصم:'),
            'grand_total' => $processArabic('الإجمالي النهائي:'),
            'paid' => $processArabic('المدفوع:'),
            'remaining' => $processArabic('المتبقي:'),
            'status' => $processArabic('الحالة:'),
            'status_paid' => $processArabic('مدفوعة بالكامل'),
            'status_partial' => $processArabic('مدفوعة جزئياً'),
            'status_unpaid' => $processArabic('غير مدفوعة'),
            'notes_title' => $processArabic('ملاحظات:'),
            'thank_you' => $processArabic('شكراً لتعاملكم معنا'),
            'printed_at' => $processArabic('تم الطباعة بتاريخ:'),
        ];

        // Prepare data for the view with processed Arabic text
        $data = [
            'invoice' => $invoice,
            'companySettings' => $companySettings,
            'labels' => $labels,
            'processedData' => [
                'company_name' => $processedCompanyName,
                'company_address' => $processedCompanyAddress,
                'company_email' => $processedCompanyEmail,
                'partner_name' => $processedPartnerName,
                'partner_phone' => $processedPartnerPhone,
                'partner_address' => $processedPartnerAddress,
                'warehouse_name' => $processedWarehouseName,
                'warehouse_address' => $processedWarehouseAddress,
                'items' => $processedItems,
                'notes' => $processedNotes,
            ],
        ];

        // Generate PDF with configuration
        $pdf = Pdf::loadView('invoices.print', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'amiri');

        // Return PDF as inline (opens in browser)
        return $pdf->stream("invoice-{$invoice->invoice_number}.pdf");
    }
}
