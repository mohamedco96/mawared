<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Settings\CompanySettings;
use ArPHP\I18N\Arabic;
use Barryvdh\DomPDF\Facade\Pdf;

class PublicQuotationController extends Controller
{
    public function show(string $token)
    {
        $quotation = Quotation::with([
            'partner',
            'items.product' => function ($query) {
                $query->withTrashed();
            },
            'items.product.smallUnit',
            'items.product.largeUnit',
            'items.product.category',
        ])
        ->where('public_token', $token)
        ->firstOrFail();

        $companySettings = app(CompanySettings::class);

        return view('quotations.public-catalog', [
            'quotation' => $quotation,
            'companySettings' => $companySettings,
            'isExpired' => $quotation->isExpired(),
        ]);
    }

    public function downloadPdf(string $token)
    {
        $quotation = Quotation::with([
            'partner',
            'items.product' => function ($query) {
                $query->withTrashed();
            },
            'items.product.smallUnit',
            'items.product.largeUnit',
        ])
        ->where('public_token', $token)
        ->firstOrFail();

        $companySettings = app(CompanySettings::class);

        // Process Arabic text for PDF (using Arphp\I18N\Arabic pattern from InvoiceController)
        $arabic = new Arabic();

        // Process all Arabic text fields
        $processedData = [
            'quotation' => $quotation,
            'companySettings' => $companySettings,
            'company_name' => $arabic->utf8Glyphs($companySettings->company_name),
            'customer_name' => $arabic->utf8Glyphs($quotation->customer_name),
            'notes' => $quotation->notes ? $arabic->utf8Glyphs($quotation->notes) : '',
        ];

        // Process items
        $processedItems = $quotation->items->map(function ($item) use ($arabic) {
            return [
                'product_name' => $arabic->utf8Glyphs($item->product_name),
                'unit_name' => $arabic->utf8Glyphs($item->unit_name),
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ];
        });

        $processedData['items'] = $processedItems;

        $pdf = Pdf::loadView('quotations.public-pdf', $processedData)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true)
            ->setOption('defaultFont', 'amiri');

        return $pdf->download("quotation-{$quotation->quotation_number}.pdf");
    }
}
