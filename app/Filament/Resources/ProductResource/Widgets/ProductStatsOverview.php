<?php

namespace App\Filament\Resources\ProductResource\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ProductStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.product-resource.widgets.product-stats-overview';

    protected function getStats(): array
    {
        // Recently Added (Last 30 days)
        $recentlyAdded = Product::where('created_at', '>=', now()->subDays(30))->count();

        // Top Selling Product (Last 30 days)
        $topProduct = Product::query()
            ->select('products.name')
            ->leftJoin('sales_invoice_items', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->where('sales_invoices.status', 'posted')
            ->whereDate('sales_invoices.created_at', '>=', now()->subDays(30))
            ->whereNull('sales_invoices.deleted_at')
            ->groupBy('products.id', 'products.name')
            ->orderByRaw('SUM(sales_invoice_items.quantity) DESC')
            ->limit(1)
            ->first();

        // Total Inventory Cost
        $totalCost = DB::table('products')
            ->joinSub(
                DB::table('stock_movements')
                    ->select('product_id', DB::raw('SUM(quantity) as current_stock'))
                    ->whereNull('deleted_at')
                    ->groupBy('product_id'),
                'stocks',
                'products.id',
                '=',
                'stocks.product_id'
            )
            ->whereNull('products.deleted_at')
            ->where('stocks.current_stock', '>', 0)
            ->sum(DB::raw('stocks.current_stock * COALESCE(products.avg_cost, 0)'));

        return [
            Stat::make('قيمة المخزون', number_format($totalCost, 2))
                ->description('إجمالي تكلفة المخزون الحالي')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('منتجات جديدة', $recentlyAdded)
                ->description('تمت إضافتها خلال 30 يوم')
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('success'),

            Stat::make('الأكثر مبيعاً', $topProduct?->name ?? '—')
                ->description('المنتج الأكثر طلباً (30 يوم)')
                ->descriptionIcon('heroicon-m-trophy')
                ->color('info'),
        ];
    }
}
