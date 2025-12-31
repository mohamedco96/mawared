<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Settings\CompanySettings;
use Arphp\Glyphs;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Print Partner Statement PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function printPartnerStatement(Request $request)
    {
        // Validate parameters
        $validated = $request->validate([
            'partner_id' => 'required|exists:partners,id',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        // Get report data
        $service = app(ReportService::class);
        $reportData = $service->getPartnerStatement(
            $validated['partner_id'],
            $validated['from_date'],
            $validated['to_date']
        );

        // Get company settings
        $companySettings = app(CompanySettings::class);

        // Initialize Arabic text processor
        $arabic = new Glyphs();
        $processArabic = function ($text) use ($arabic) {
            return $text ? $arabic->utf8Glyphs($text, 1000, false) : '';
        };

        // Process company info
        $processedData = [
            'company_name' => $processArabic($companySettings->company_name),
            'company_address' => $processArabic($companySettings->company_address),
            'partner_name' => $processArabic($reportData['partner']->name),
            'partner_phone' => $processArabic($reportData['partner']->phone ?? 'غير متوفر'),
        ];

        // Process labels
        $labels = [
            'report_title' => $processArabic('كشف حساب عميل'),
            'customer_name' => $processArabic('اسم العميل:'),
            'phone' => $processArabic('الهاتف:'),
            'from_date' => $processArabic('من تاريخ:'),
            'to_date' => $processArabic('إلى تاريخ:'),
            'date' => $processArabic('التاريخ'),
            'reference' => $processArabic('رقم المرجع'),
            'description' => $processArabic('البيان'),
            'debit' => $processArabic('مدين'),
            'credit' => $processArabic('دائن'),
            'balance' => $processArabic('الرصيد'),
            'opening_balance' => $processArabic('رصيد أول المدة'),
            'total' => $processArabic('الإجمالي'),
            'printed_at' => $processArabic('تم الطباعة بتاريخ:'),
        ];

        // Process transaction descriptions
        $reportData['transactions'] = $reportData['transactions']->map(function ($transaction) use ($processArabic) {
            $transaction['description'] = $processArabic($transaction['description']);
            if (isset($transaction['warehouse'])) {
                $transaction['warehouse'] = $processArabic($transaction['warehouse']);
            }
            return $transaction;
        });

        // Prepare data for view
        $data = [
            'reportData' => $reportData,
            'companySettings' => $companySettings,
            'processedData' => $processedData,
            'labels' => $labels,
        ];

        // Generate PDF
        $pdf = Pdf::loadView('reports.partner-statement', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'amiri');

        return $pdf->stream("partner-statement-{$reportData['partner']->name}-{$validated['from_date']}.pdf");
    }

    /**
     * Print Stock Card PDF
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function printStockCard(Request $request)
    {
        // Validate parameters
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        // Get report data
        $service = app(ReportService::class);
        $reportData = $service->getStockCard(
            $validated['product_id'],
            $validated['warehouse_id'] ?? 'all',
            $validated['from_date'],
            $validated['to_date']
        );

        // Get company settings
        $companySettings = app(CompanySettings::class);

        // Initialize Arabic text processor
        $arabic = new Glyphs();
        $processArabic = function ($text) use ($arabic) {
            return $text ? $arabic->utf8Glyphs($text, 1000, false) : '';
        };

        // Process company info
        $processedData = [
            'company_name' => $processArabic($companySettings->company_name),
            'company_address' => $processArabic($companySettings->company_address),
            'product_name' => $processArabic($reportData['product']->name),
            'product_sku' => $reportData['product']->sku,
            'warehouse_name' => $reportData['warehouse']
                ? $processArabic($reportData['warehouse']->name)
                : $processArabic('جميع المخازن'),
        ];

        // Process labels
        $labels = [
            'report_title' => $processArabic('كارت الصنف'),
            'product_name' => $processArabic('اسم المنتج:'),
            'product_code' => $processArabic('كود المنتج:'),
            'warehouse' => $processArabic('المخزن:'),
            'from_date' => $processArabic('من تاريخ:'),
            'to_date' => $processArabic('إلى تاريخ:'),
            'date' => $processArabic('التاريخ'),
            'type' => $processArabic('النوع'),
            'reference' => $processArabic('رقم المرجع'),
            'warehouse_col' => $processArabic('المخزن'),
            'in' => $processArabic('وارد'),
            'out' => $processArabic('صادر'),
            'cost' => $processArabic('التكلفة'),
            'balance' => $processArabic('الرصيد'),
            'opening_stock' => $processArabic('رصيد أول المدة'),
            'total' => $processArabic('الإجمالي'),
            'printed_at' => $processArabic('تم الطباعة بتاريخ:'),
        ];

        // Process movement data
        $reportData['movements'] = $reportData['movements']->map(function ($movement) use ($processArabic) {
            $movement['type'] = $processArabic($movement['type']);
            $movement['warehouse'] = $processArabic($movement['warehouse']);
            return $movement;
        });

        // Prepare data for view
        $data = [
            'reportData' => $reportData,
            'companySettings' => $companySettings,
            'processedData' => $processedData,
            'labels' => $labels,
        ];

        // Generate PDF
        $pdf = Pdf::loadView('reports.stock-card', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'amiri');

        return $pdf->stream("stock-card-{$reportData['product']->name}-{$validated['from_date']}.pdf");
    }
}
