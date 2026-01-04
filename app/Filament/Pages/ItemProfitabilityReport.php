<?php

namespace App\Filament\Pages;

use App\Models\Product;
use App\Models\SalesInvoiceItem;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ItemProfitabilityReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string $view = 'filament.pages.item-profitability-report';

    protected static ?string $navigationLabel = 'تحليل ربحية الأصناف';

    protected static ?string $title = 'تقرير ربحية الأصناف';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_ItemProfitabilityReport') ?? false;
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth(),
            'to_date' => now()->endOfMonth(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->filters($this->getTableFilters())
            ->defaultSort('total_qty_sold', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll(false);
    }

    protected function getTableQuery(): Builder
    {
        $fromDate = $this->data['from_date'] ?? now()->startOfMonth();
        $toDate = $this->data['to_date'] ?? now()->endOfMonth();

        return Product::query()
            ->select([
                'products.id',
                'products.name',
                DB::raw('COALESCE(SUM(sales_invoice_items.quantity), 0) as total_qty_sold'),
                DB::raw('COALESCE(SUM(sales_invoice_items.total), 0) as total_revenue'),
                DB::raw('COALESCE(SUM(
                    CASE
                        WHEN sales_invoice_items.unit_type = "large"
                        THEN stock_movements.cost_at_time * sales_invoice_items.quantity * products.factor
                        ELSE stock_movements.cost_at_time * sales_invoice_items.quantity
                    END
                ), 0) as total_cost'),
                DB::raw('(COALESCE(SUM(sales_invoice_items.total), 0) -
                         COALESCE(SUM(
                             CASE
                                 WHEN sales_invoice_items.unit_type = "large"
                                 THEN stock_movements.cost_at_time * sales_invoice_items.quantity * products.factor
                                 ELSE stock_movements.cost_at_time * sales_invoice_items.quantity
                             END
                         ), 0)) as profit'),
                DB::raw('CASE
                    WHEN SUM(sales_invoice_items.total) > 0
                    THEN ((SUM(sales_invoice_items.total) -
                           SUM(
                               CASE
                                   WHEN sales_invoice_items.unit_type = "large"
                                   THEN stock_movements.cost_at_time * sales_invoice_items.quantity * products.factor
                                   ELSE stock_movements.cost_at_time * sales_invoice_items.quantity
                               END
                           )) / SUM(sales_invoice_items.total) * 100)
                    ELSE 0
                END as profit_margin_pct'),
            ])
            ->leftJoin('sales_invoice_items', 'products.id', '=', 'sales_invoice_items.product_id')
            ->leftJoin('sales_invoices', function ($join) use ($fromDate, $toDate) {
                $join->on('sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
                    ->where('sales_invoices.status', '=', 'posted')
                    ->whereNull('sales_invoices.deleted_at')
                    ->whereDate('sales_invoices.created_at', '>=', $fromDate)
                    ->whereDate('sales_invoices.created_at', '<=', $toDate);
            })
            ->leftJoin('stock_movements', function ($join) {
                $join->on('stock_movements.reference_type', '=', DB::raw("'App\\\\Models\\\\SalesInvoiceItem'"))
                    ->on('stock_movements.reference_id', '=', 'sales_invoice_items.id')
                    ->whereNull('stock_movements.deleted_at');
            })
            ->groupBy('products.id', 'products.name')
            ->having('total_revenue', '>', 0);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('name')
                ->label('اسم المنتج')
                ->searchable()
                ->sortable()
                ->weight('medium'),

            Tables\Columns\TextColumn::make('total_qty_sold')
                ->label('الكمية المباعة')
                ->sortable()
                ->alignCenter()
                ->badge()
                ->color('info'),

            Tables\Columns\TextColumn::make('total_revenue')
                ->label('إجمالي الإيرادات')
                ->money('EGP', locale: 'ar_EG')
                ->sortable()
                ->color('success')
                ->alignEnd(),

            Tables\Columns\TextColumn::make('total_cost')
                ->label('إجمالي التكلفة')
                ->money('EGP', locale: 'ar_EG')
                ->sortable()
                ->color('warning')
                ->alignEnd()
                ->visible(fn () => auth()->user()->can('view_profit')),

            Tables\Columns\TextColumn::make('profit')
                ->label('صافي الربح')
                ->money('EGP', locale: 'ar_EG')
                ->sortable()
                ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                ->alignEnd()
                ->weight('bold')
                ->visible(fn () => auth()->user()->can('view_profit')),

            Tables\Columns\TextColumn::make('profit_margin_pct')
                ->label('هامش الربح %')
                ->formatStateUsing(fn ($state) => number_format($state, 2).'%')
                ->sortable()
                ->badge()
                ->color(fn ($state) => match (true) {
                    $state >= 30 => 'success',
                    $state >= 15 => 'warning',
                    default => 'danger',
                })
                ->alignCenter()
                ->visible(fn () => auth()->user()->can('view_profit')),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('category_id')
                ->label('التصنيف')
                ->relationship('category', 'name')
                ->searchable()
                ->preload(),

            Tables\Filters\SelectFilter::make('profitability')
                ->label('مستوى الربحية')
                ->options([
                    'high' => 'عالي (≥30%)',
                    'medium' => 'متوسط (15-30%)',
                    'low' => 'منخفض (<15%)',
                ])
                ->query(function ($query, array $data) {
                    if (! isset($data['value'])) {
                        return $query;
                    }

                    return $query->havingRaw(match ($data['value']) {
                        'high' => 'profit_margin_pct >= 30',
                        'medium' => 'profit_margin_pct >= 15 AND profit_margin_pct < 30',
                        'low' => 'profit_margin_pct < 15',
                        default => '1=1',
                    });
                })
                ->visible(fn () => auth()->user()->can('view_profit')),
        ];
    }

    protected function getForms(): array
    {
        return [
            'form' => $this->makeForm()
                ->schema([
                    Forms\Components\Section::make('فترة التقرير')
                        ->schema([
                            Forms\Components\DatePicker::make('from_date')
                                ->label('من تاريخ')
                                ->required()
                                ->default(now()->startOfMonth())
                                ->reactive(),
                            Forms\Components\DatePicker::make('to_date')
                                ->label('إلى تاريخ')
                                ->required()
                                ->default(now()->endOfMonth())
                                ->reactive(),
                        ])
                        ->columns(2),
                ])
                ->statePath('data'),
        ];
    }
}
