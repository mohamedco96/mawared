<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    
    protected static ?string $navigationLabel = 'حركات المخزون';
    
    protected static ?string $modelLabel = 'حركة مخزون';
    
    protected static ?string $pluralModelLabel = 'حركات المخزون';
    
    protected static bool $shouldRegisterNavigation = false; // Hide from navigation, use as report

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name', fn ($query) => $query->where('is_active', true))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => Warehouse::where('is_active', true)->first()?->id ?? Warehouse::first()?->id),
                Forms\Components\Select::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable(['name', 'barcode', 'sku'])
                    ->getSearchResultsUsing(function (?string $search): array {
                        $query = Product::query();

                        if (!empty($search)) {
                            $query->where(function ($q) use ($search) {
                                $q->where('name', 'like', "%{$search}%")
                                  ->orWhere('sku', 'like', "%{$search}%")
                                  ->orWhere('barcode', 'like', "%{$search}%");
                            });
                        } else {
                            // Load latest products when no search
                            $query->latest();
                        }

                        return $query->limit(10)->pluck('name', 'id')->toArray();
                    })
                    ->getOptionLabelUsing(function ($value): string {
                        $product = Product::find($value);
                        return $product ? $product->name : '';
                    })
                    ->loadingMessage('جاري التحميل...')
                    ->searchPrompt('ابحث عن منتج بالاسم أو الباركود أو SKU')
                    ->noSearchResultsMessage('لم يتم العثور على منتجات')
                    ->searchingMessage('جاري البحث...')
                    ->preload(),
                Forms\Components\Select::make('type')
                    ->label('النوع')
                    ->options([
                        'sale' => 'بيع',
                        'purchase' => 'شراء',
                        'sale_return' => 'مرتجع بيع',
                        'purchase_return' => 'مرتجع شراء',
                        'adjustment_in' => 'إضافة',
                        'adjustment_out' => 'خصم',
                        'transfer' => 'نقل',
                    ])
                    ->required()
                    ->native(false),
                Forms\Components\TextInput::make('quantity')
                    ->label('الكمية')
                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                    ->required(),
                Forms\Components\TextInput::make('cost_at_time')
                    ->label('التكلفة')
                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                    ->step(0.01)
                    ->required(),
                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['warehouse', 'product']))
            ->columns([
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
                        'sale_return' => 'مرتجع بيع',
                        'purchase_return' => 'مرتجع شراء',
                        'adjustment_in' => 'إضافة',
                        'adjustment_out' => 'خصم',
                        'transfer' => 'نقل',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'sale', 'adjustment_out' => 'danger',
                        'purchase', 'adjustment_in' => 'success',
                        'sale_return', 'purchase_return' => 'warning',
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
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_type')
                    ->label('المصدر')
                    ->formatStateUsing(fn (?string $state): string => match($state) {
                        'sales_invoice' => 'فاتورة بيع',
                        'purchase_invoice' => 'فاتورة شراء',
                        'sales_return' => 'مرتجع بيع',
                        'purchase_return' => 'مرتجع شراء',
                        'stock_adjustment' => 'تسوية',
                        'warehouse_transfer' => 'نقل',
                        default => $state ?? '—',
                    })
                    ->badge(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(30)
                    ->tooltip(fn (StockMovement $record): string => $record->notes ?? ''),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
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
                        'sale_return' => 'مرتجع بيع',
                        'purchase_return' => 'مرتجع شراء',
                        'adjustment_in' => 'إضافة',
                        'adjustment_out' => 'خصم',
                        'transfer' => 'نقل',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('reference_type')
                    ->label('المصدر')
                    ->options([
                        'sales_invoice' => 'فاتورة بيع',
                        'purchase_invoice' => 'فاتورة شراء',
                        'sales_return' => 'مرتجع بيع',
                        'purchase_return' => 'مرتجع شراء',
                        'stock_adjustment' => 'تسوية',
                        'warehouse_transfer' => 'نقل',
                    ])
                    ->native(false),
                Tables\Filters\Filter::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ'),
                        Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query, $date) => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn ($query, $date) => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('quantity')
                    ->label('الكمية')
                    ->form([
                        Forms\Components\Select::make('direction')
                            ->label('الاتجاه')
                            ->options([
                                'positive' => 'إضافة (موجب)',
                                'negative' => 'خصم (سالب)',
                            ])
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (!isset($data['direction'])) {
                            return $query;
                        }

                        return $query->when(
                            $data['direction'] === 'positive',
                            fn ($q) => $q->where('quantity', '>', 0),
                            fn ($q) => $q->where('quantity', '<', 0)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockMovements::route('/'),
        ];
    }
}
