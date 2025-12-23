<?php

namespace App\Filament\Pages;

use App\Models\StockMovement;
use App\Models\TreasuryTransaction;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DailyOperations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    
    protected static string $view = 'filament.pages.daily-operations';
    
    protected static ?string $navigationLabel = 'العمليات اليومية';
    
    protected static ?string $title = 'العمليات اليومية';
    
    protected static ?int $navigationSort = 1;

    public function getTabs(): array
    {
        return [
            'sales' => Tables\Tabs\Tab::make('تدفق المبيعات')
                ->badge(fn () => TreasuryTransaction::where('type', 'collection')
                    ->whereDate('created_at', today())
                    ->count()),
            'cashflow' => Tables\Tabs\Tab::make('التدفق النقدي')
                ->badge(fn () => TreasuryTransaction::whereDate('created_at', today())
                    ->count()),
            'stock' => Tables\Tabs\Tab::make('سجل المخزون')
                ->badge(fn () => StockMovement::whereDate('created_at', today())
                    ->count()),
        ];
    }

    public function table(Table $table): Table
    {
        $activeTab = $this->activeTab ?? request()->get('tab', 'sales');

        return $table
            ->query($this->getTableQuery($activeTab))
            ->columns($this->getTableColumns($activeTab))
            ->filters($this->getTableFilters($activeTab))
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }

    public function getTabUrl(string $tab): string
    {
        return $this->getUrl(['tab' => $tab]);
    }

    protected function getTableQuery(string $tab): Builder
    {
        return match($tab) {
            'sales' => TreasuryTransaction::query()
                ->where('type', 'collection')
                ->with(['treasury', 'partner', 'reference']),
            'cashflow' => TreasuryTransaction::query()
                ->with(['treasury', 'partner', 'reference']),
            'stock' => StockMovement::query()
                ->with(['warehouse', 'product', 'reference']),
            default => TreasuryTransaction::query(),
        };
    }

    protected function getTableColumns(string $tab): array
    {
        return match($tab) {
            'sales' => [
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SAR')
                    ->sortable()
                    ->color('success'),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(50)
                    ->tooltip(fn (TreasuryTransaction $record): string => $record->description),
                Tables\Columns\TextColumn::make('reference_type')
                    ->label('المصدر')
                    ->formatStateUsing(fn (?string $state): string => match($state) {
                        'sales_invoice' => 'فاتورة بيع',
                        'financial_transaction' => 'معاملة مالية',
                        default => $state ?? '—',
                    })
                    ->badge(),
            ],
            'cashflow' => [
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'collection' => 'تحصيل',
                        'payment' => 'دفع',
                        'income' => 'إيراد',
                        'expense' => 'مصروف',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'collection', 'income' => 'success',
                        'payment', 'expense' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('treasury.name')
                    ->label('الخزينة')
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('الشريك')
                    ->searchable()
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('SAR')
                    ->sortable()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(50)
                    ->tooltip(fn (TreasuryTransaction $record): string => $record->description),
            ],
            'stock' => [
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'sale' => 'بيع',
                        'purchase' => 'شراء',
                        'adjustment_in' => 'إضافة',
                        'adjustment_out' => 'خصم',
                        'transfer' => 'نقل',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'sale', 'adjustment_out' => 'danger',
                        'purchase', 'adjustment_in' => 'success',
                        'transfer' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('الكمية')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('cost_at_time')
                    ->label('التكلفة')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_type')
                    ->label('المصدر')
                    ->formatStateUsing(fn (?string $state): string => match($state) {
                        'sales_invoice' => 'فاتورة بيع',
                        'purchase_invoice' => 'فاتورة شراء',
                        'stock_adjustment' => 'تسوية',
                        'warehouse_transfer' => 'نقل',
                        default => $state ?? '—',
                    })
                    ->badge(),
            ],
            default => [],
        };
    }

    protected function getTableFilters(string $tab): array
    {
        return match($tab) {
            'sales', 'cashflow' => [
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ],
            'stock' => [
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'sale' => 'بيع',
                        'purchase' => 'شراء',
                        'adjustment_in' => 'إضافة',
                        'adjustment_out' => 'خصم',
                        'transfer' => 'نقل',
                    ])
                    ->native(false),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ],
            default => [],
        };
    }
}
