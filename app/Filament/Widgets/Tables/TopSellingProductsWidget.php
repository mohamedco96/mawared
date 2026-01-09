<?php

namespace App\Filament\Widgets\Tables;

use App\Models\Product;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopSellingProductsWidget extends BaseWidget
{
    protected static ?string $heading = 'الأكثر مبيعاً (Top Selling)';

    protected static ?int $sort = 7;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_TopSellingProductsWidget') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        // Get top selling products by joining with sales invoice items
        return Product::query()
            ->select([
                'products.id',
                'products.name as product_name',
                DB::raw('COALESCE(SUM(sales_invoice_items.quantity), 0) as total_sold'),
                DB::raw('COALESCE(SUM(sales_invoice_items.total), 0) as total_revenue'),
            ])
            ->leftJoin('sales_invoice_items', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('sales_invoices', function ($join) {
                $join->on('sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                    ->where('sales_invoices.status', '=', 'posted')
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
                ->searchable(false)
                ->sortable(false),

            Tables\Columns\TextColumn::make('total_sold')
                ->label('إجمالي الكمية')
                ->weight('bold')
                ->color('success')
                ->alignCenter()
                ->sortable(false),

            Tables\Columns\TextColumn::make('total_revenue')
                ->label('إجمالي الإيرادات')
                ->formatStateUsing(fn ($state) => number_format($state, 2))
                ->color('info')
                ->alignEnd()
                ->sortable(false),
        ];
    }
}
