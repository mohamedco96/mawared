<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ReportService;
use App\Settings\CompanySettings;
use App\Settings\PrintSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Print Partner Statement
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

        // Get settings
        $companySettings = app(CompanySettings::class);
        $printSettings = app(PrintSettings::class);

        // Get format from query parameter (default: a4)
        $format = request()->query('format', 'a4');
        if (!in_array($format, ['a4', 'thermal'])) {
            $format = 'a4';
        }

        // Prepare data for view (NO Arabic processing!)
        $data = [
            'reportData' => $reportData,
            'companySettings' => $companySettings,
            'format' => $format,
            'autoPrint' => $printSettings->auto_print_enabled,
        ];

        // Return simple view
        return view('reports.partner-statement', $data);
    }

    /**
     * Print Stock Card
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

        // Get settings
        $companySettings = app(CompanySettings::class);
        $printSettings = app(PrintSettings::class);

        // Get format from query parameter (default: a4)
        $format = request()->query('format', 'a4');
        if (!in_array($format, ['a4', 'thermal'])) {
            $format = 'a4';
        }

        // Prepare data for view (NO Arabic processing!)
        $data = [
            'reportData' => $reportData,
            'companySettings' => $companySettings,
            'format' => $format,
            'autoPrint' => $printSettings->auto_print_enabled,
        ];

        // Return simple view
        return view('reports.stock-card', $data);
    }

    /**
     * Print Low Stock Report
     *
     * @return \Illuminate\Http\Response
     */
    public function lowStockPrint()
    {
        // Get products where current stock <= min_stock
        $products = Product::query()
            ->whereRaw('(
                SELECT COALESCE(SUM(quantity), 0)
                FROM stock_movements
                WHERE stock_movements.product_id = products.id
                AND stock_movements.deleted_at IS NULL
            ) <= min_stock')
            ->orderByRaw('(
                SELECT COALESCE(SUM(quantity), 0)
                FROM stock_movements
                WHERE stock_movements.product_id = products.id
                AND stock_movements.deleted_at IS NULL
            ) ASC')
            ->with(['smallUnit', 'largeUnit'])
            ->get()
            ->map(function ($product) {
                $product->current_stock = DB::table('stock_movements')
                    ->where('product_id', $product->id)
                    ->whereNull('deleted_at')
                    ->sum('quantity') ?? 0;

                return $product;
            });

        // Get settings
        $companySettings = app(CompanySettings::class);
        $printSettings = app(PrintSettings::class);

        return view('reports.low-stock-print', [
            'products' => $products,
            'companySettings' => $companySettings,
            'format' => 'a4',
            'autoPrint' => $printSettings->auto_print_enabled,
        ]);
    }
}
