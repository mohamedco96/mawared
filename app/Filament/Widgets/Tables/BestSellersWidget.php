<?php

namespace App\Filament\Widgets\Tables;

use App\Filament\Pages\ItemProfitabilityReport;
use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BestSellersWidget extends BaseWidget
{
    protected static ?string $heading = 'الأكثر مبيعاً (Best Sellers)';

    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_BestSellersWidget') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        // Get date range: last 30 days by default
        $fromDate = now()->subDays(30);

        return Product::query()
            ->select([
                'products.id',
                'products.name as product_name',
                DB::raw('COALESCE(SUM(sales_invoice_items.quantity), 0) as total_sold'),
                DB::raw('COALESCE(SUM(sales_invoice_items.total), 0) as total_revenue'),
            ])
            ->leftJoin('sales_invoice_items', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('sales_invoices', function ($join) use ($fromDate) {
                $join->on('sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                    ->where('sales_invoices.status', '=', 'posted')
                    ->whereDate('sales_invoices.created_at', '>=', $fromDate)
                    ->whereNull('sales_invoices.deleted_at');
            })
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_sold')
            ->having('total_sold', '>', 0)
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('product_name')
                ->label('اسم المنتج')
                ->weight('medium')
                ->icon('heroicon-o-trophy')
                ->searchable(false)
                ->sortable(false),

            Tables\Columns\TextColumn::make('total_sold')
                ->label('الكمية المباعة')
                ->weight('bold')
                ->color('success')
                ->badge()
                ->alignCenter()
                ->sortable(false),

            Tables\Columns\TextColumn::make('total_revenue')
                ->label('إجمالي الإيرادات')
                ->formatStateUsing(fn ($state) => number_format($state, 2))
                ->weight('bold')
                ->color('info')
                ->alignEnd()
                ->sortable(false)
                ->hidden(fn () => !auth()->user()?->can('view_profit') ?? false)
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('view_all')
                ->label('عرض الكل')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('primary')
                ->url(fn () => ItemProfitabilityReport::getUrl([
                    'tableSearch' => '',
                    'tableSortColumn' => 'total_qty_sold',
                    'tableSortDirection' => 'desc',
                ])),
        ];
    }
}
