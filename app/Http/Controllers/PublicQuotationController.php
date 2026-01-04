<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Settings\CompanySettings;
use App\Settings\PrintSettings;

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
        $printSettings = app(PrintSettings::class);

        // Get format from query parameter (default: a4)
        $format = request()->query('format', 'a4');
        if (!in_array($format, ['a4', 'thermal'])) {
            $format = 'a4';
        }

        // Prepare data for view (NO Arabic processing!)
        $data = [
            'quotation' => $quotation,
            'companySettings' => $companySettings,
            'format' => $format,
            'autoPrint' => $printSettings->auto_print_enabled,
        ];

        // Return simple view (browser handles print/download)
        return view('quotations.public-pdf', $data);
    }
}
