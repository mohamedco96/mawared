<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OperationalStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_OperationalStatsWidget') ?? false;
    }

    protected function getStats(): array
    {
        return Cache::remember('dashboard.operational_stats', 300, function () {
            // 1. Low Stock Alerts: Count where current_stock <= min_stock
            $lowStockCount = DB::table('products')
                ->whereRaw('(
                    SELECT COALESCE(SUM(quantity), 0)
                    FROM stock_movements
                    WHERE stock_movements.product_id = products.id
                    AND stock_movements.deleted_at IS NULL
                ) <= products.min_stock')
                ->whereNull('deleted_at')
                ->count();

            // 2. Today's Sales Count: Posted invoices only (count, not amount)
            $todaySalesCount = SalesInvoice::where('status', 'posted')
                ->whereDate('created_at', today())
                ->count();

            // 3. Month Purchases Count: Posted invoices only (count, not amount)
            $monthPurchasesCount = PurchaseInvoice::where('status', 'posted')
                ->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth(),
                ])
                ->count();

            return [
                Stat::make('نواقص المخزون', $lowStockCount)
                    ->description('منتجات تحتاج إعادة طلب')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color($lowStockCount > 0 ? 'danger' : 'success'),

                Stat::make('مبيعات اليوم', $todaySalesCount)
                    ->description('عدد فواتير المبيعات - ' . now()->format('d/m/Y'))
                    ->icon('heroicon-o-shopping-cart')
                    ->color('success'),

                Stat::make('مشتريات الشهر', $monthPurchasesCount)
                    ->description('عدد فواتير المشتريات - من ' . now()->startOfMonth()->format('d/m') . ' إلى ' . now()->format('d/m'))
                    ->icon('heroicon-o-shopping-bag')
                    ->color('warning'),
            ];
        });
    }
}
