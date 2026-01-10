<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Settings\CompanySettings;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ShowroomController extends Controller
{
    public function show(string $mode)
    {
        // Validate mode
        if (! in_array($mode, ['retail', 'wholesale'])) {
            abort(404);
        }

        // Get company settings for WhatsApp
        $companySettings = app(CompanySettings::class);

        // Warning if WhatsApp not configured
        $whatsappConfigured = ! empty($companySettings->business_whatsapp_number);

        // Get all catalog products with stock > 0
        $products = Product::query()
            ->with(['smallUnit', 'largeUnit', 'category'])
            ->where('is_visible_in_catalog', true)
            ->withSum('stockMovements', 'quantity')
            ->get()
            ->filter(function ($product) {
                return ($product->stock_movements_sum_quantity ?? 0) > 0;
            })
            ->sortBy('name')
            ->values();

        return view('showroom.index', [
            'mode' => $mode,
            'products' => $products,
            'companySettings' => $companySettings,
            'whatsappConfigured' => $whatsappConfigured,
        ]);
    }

    public function downloadQr(string $mode, Request $request)
    {
        // Validate mode
        if (! in_array($mode, ['retail', 'wholesale'])) {
            abort(404);
        }

        $url = route('showroom.'.$mode);
        $format = $request->query('format', 'svg');

        if ($format === 'png') {
            $qr = QrCode::format('png')
                ->size(500)
                ->margin(2)
                ->errorCorrection('H')
                ->generate($url);

            return response()->streamDownload(function () use ($qr) {
                echo $qr;
            }, 'catalog-'.$mode.'-qr.png', [
                'Content-Type' => 'image/png',
            ]);
        }

        // Default: SVG
        $qr = QrCode::format('svg')
            ->size(500)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($url);

        return response()->streamDownload(function () use ($qr) {
            echo $qr;
        }, 'catalog-'.$mode.'-qr.svg', [
            'Content-Type' => 'image/svg+xml',
        ]);
    }
}
