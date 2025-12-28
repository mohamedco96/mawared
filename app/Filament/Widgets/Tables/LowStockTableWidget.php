<?php

namespace App\Filament\Widgets\Tables;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Closure;
use Filament\Tables;
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

    protected function getTableQuery(): Builder
    {
        return Product::query()
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
                ->getStateUsing(function (Product $record): int {
                    return DB::table('stock_movements')
                        ->where('product_id', $record->id)
                        ->whereNull('deleted_at')
                        ->sum('quantity') ?? 0;
                })
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
}
