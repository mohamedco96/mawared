<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OperationsOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_OperationsOverviewWidget') ?? false;
    }

    protected function getStats(): array
    {
        return Cache::remember('dashboard.operations_overview', 300, function () {
            // 1. Inventory Value: Sum of (current stock × avg_cost) for all products
            $inventoryValue = DB::table('products')
                ->selectRaw('SUM(
                    (SELECT COALESCE(SUM(quantity), 0)
                     FROM stock_movements
                     WHERE stock_movements.product_id = products.id
                     AND stock_movements.deleted_at IS NULL)
                    * products.avg_cost
                ) as total_value')
                ->whereNull('deleted_at')
                ->value('total_value') ?? 0;

            // 2. Low Stock Alerts: Count where current_stock <= min_stock
            $lowStockCount = DB::table('products')
                ->whereRaw('(
                    SELECT COALESCE(SUM(quantity), 0)
                    FROM stock_movements
                    WHERE stock_movements.product_id = products.id
                    AND stock_movements.deleted_at IS NULL
                ) <= products.min_stock')
                ->whereNull('deleted_at')
                ->count();

            // 3. Today's Sales: Posted invoices only
            $todaySales = SalesInvoice::where('status', 'posted')
                ->whereDate('created_at', today())
                ->sum('total') ?? 0;

            // 4. Month Purchases: Posted invoices only
            $monthPurchases = PurchaseInvoice::where('status', 'posted')
                ->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
                ->sum('total') ?? 0;

            return [
                Stat::make('قيمة المخزون', number_format($inventoryValue, 2))
                    ->description('إجمالي قيمة المخزون الحالي')
                    ->icon('heroicon-o-cube')
                    ->color('info'),

                Stat::make('نواقص المخزون', $lowStockCount)
                    ->description('منتجات تحتاج إعادة طلب')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color($lowStockCount > 0 ? 'danger' : 'success'),

                Stat::make('مبيعات اليوم', number_format($todaySales, 2))
                    ->description(now()->format('d/m/Y'))
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success'),

                Stat::make('مشتريات الشهر', number_format($monthPurchases, 2))
                    ->description('من ' . now()->startOfMonth()->format('d/m') . ' إلى ' . now()->format('d/m'))
                    ->icon('heroicon-o-shopping-bag')
                    ->color('warning'),
            ];
        });
    }
}
