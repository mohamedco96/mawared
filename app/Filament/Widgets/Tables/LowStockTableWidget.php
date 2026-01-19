<?php

namespace App\Filament\Widgets\Tables;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Closure;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class LowStockTableWidget extends BaseWidget
{
    protected static ?string $heading = 'تنبيهات النواقص (Low Stock)';

    protected static ?int $sort = 6;

    protected int | string | array $columnSpan = ['md' => 1, 'xl' => 1];

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('widget_LowStockTableWidget') ?? false;
    }

    protected function getTableQuery(): Builder
    {
        return Product::query()
            ->addSelect(['*', 'current_stock' => function ($query) {
                $query->selectRaw('COALESCE(SUM(quantity), 0)')
                    ->from('stock_movements')
                    ->whereColumn('product_id', 'products.id')
                    ->whereNull('deleted_at');
            }])
            ->whereRaw('(
                SELECT COALESCE(SUM(quantity), 0)
                FROM stock_movements
                WHERE stock_movements.product_id = products.id
                AND stock_movements.deleted_at IS NULL
            ) <= min_stock')
            ->orderByRaw('current_stock ASC')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('اسم المنتج')
                ->searchable()
                ->weight('medium'),

            Tables\Columns\TextColumn::make('current_stock')
                ->label('المخزون الحالي')
                ->badge()
                ->color('danger')
                ->alignCenter(),

            Tables\Columns\TextColumn::make('min_stock')
                ->label('الحد الأدنى')
                ->alignCenter()
                ->color('warning'),
        ];
    }

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]);
    }

    protected function getTableHeaderActions(): array
    {
        return [
            Action::make('view_all')
                ->label('عرض الكل')
                ->icon('heroicon-o-list-bullet')
                ->color('primary')
                ->url(fn () => ProductResource::getUrl('index', [
                    'tableFilters' => [
                        'stock_level' => [
                            'level' => 'low_stock',
                        ],
                    ],
                ])),

            Action::make('print')
                ->label('طباعة')
                ->icon('heroicon-o-printer')
                ->color('success')
                ->url(fn () => route('reports.low-stock.print'))
                ->openUrlInNewTab(),
        ];
    }
}
