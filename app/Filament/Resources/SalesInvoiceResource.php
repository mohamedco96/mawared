<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Services\StockService;
use App\Services\TreasuryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesInvoiceResource extends Resource
{
    protected static ?string $model = SalesInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationLabel = 'فواتير البيع';

    protected static ?string $modelLabel = 'فاتورة بيع';

    protected static ?string $pluralModelLabel = 'فواتير البيع';

    protected static ?string $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الفاتورة')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('رقم الفاتورة')
                            ->default(fn () => 'SI-'.now()->format('Ymd').'-'.Str::random(6))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'draft' => 'مسودة',
                                'posted' => 'مؤكدة',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('المخزن')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('partner_id')
                            ->label('العميل')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'customer'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('payment_method')
                            ->label('طريقة الدفع')
                            ->options([
                                'cash' => 'نقدي',
                                'credit' => 'آجل',
                            ])
                            ->default('cash')
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('أصناف الفاتورة')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable(['name', 'barcode', 'sku'])
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get, $record) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $unitType = $get('unit_type') ?? 'small';
                                                $price = $unitType === 'large' && $product->large_retail_price
                                                    ? $product->large_retail_price
                                                    : $product->retail_price;
                                                $set('unit_price', $price);
                                                $set('quantity', 1);
                                                $set('discount', 0);
                                                $set('total', $price);
                                            }
                                        }
                                    })
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
                                Forms\Components\Select::make('unit_type')
                                    ->label('الوحدة')
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (! $productId) {
                                            return ['small' => 'صغيرة'];
                                        }
                                        $product = Product::find($productId);
                                        $options = ['small' => 'صغيرة'];
                                        if ($product && $product->large_unit_id) {
                                            $options['large'] = 'كبيرة';
                                        }

                                        return $options;
                                    })
                                    ->default('small')
                                    ->required()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $productId = $get('product_id');
                                        if ($productId && $state) {
                                            $product = Product::find($productId);
                                            if ($product) {
                                                $price = $state === 'large' && $product->large_retail_price
                                                    ? $product->large_retail_price
                                                    : $product->retail_price;
                                                $set('unit_price', $price);
                                                $quantity = $get('quantity') ?? 1;
                                                $discount = $get('discount') ?? 0;
                                                $set('total', ($price * $quantity) - $discount);
                                            }
                                        }
                                    })
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->numeric()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($unitPrice * $state) - $discount);
                                    })
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                                    ->required()
                                    ->step(0.0001)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($state * $quantity) - $discount);
                                    })
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
                                Forms\Components\TextInput::make('discount')
                                    ->label('الخصم')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.0001)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total', ($unitPrice * $quantity) - $state);
                                    })
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                                Forms\Components\Placeholder::make('stock_info')
                                    ->label('المخزون المتاح')
                                    ->content(function (Get $get) {
                                        $productId = $get('product_id');
                                        $warehouseId = $get('../../warehouse_id');
                                        if (! $productId || ! $warehouseId) {
                                            return '—';
                                        }
                                        $stockService = app(StockService::class);
                                        $stock = $stockService->getCurrentStock($warehouseId, $productId);
                                        $unitType = $get('unit_type') ?? 'small';
                                        if ($unitType === 'large' && $productId) {
                                            $product = Product::find($productId);
                                            if ($product && $product->factor > 1) {
                                                $stock = intval($stock / $product->factor);
                                            }
                                        }

                                        return $stock;
                                    })
                                    ->visible(fn (Get $get) => ! empty($get('product_id'))),
                            ])
                            ->columns(7)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['product_id'] ? Product::find($state['product_id'])?->name : null)
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                    ]),

                Forms\Components\Section::make('الإجماليات')
                    ->schema([
                        Forms\Components\Placeholder::make('calculated_subtotal')
                            ->label('المجموع الفرعي')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $subtotal += $item['total'] ?? 0;
                                }

                                return number_format($subtotal, 2).' ر.س';
                            }),
                        Forms\Components\TextInput::make('discount')
                            ->label('إجمالي الخصم')
                            ->numeric()
                            ->default(0)
                            ->step(0.0001)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $subtotal += $item['total'] ?? 0;
                                }
                                $set('subtotal', $subtotal);
                                $set('total', $subtotal - ($state ?? 0));
                            })
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Placeholder::make('calculated_total')
                            ->label('الإجمالي النهائي')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $subtotal += $item['total'] ?? 0;
                                }
                                $discount = $get('discount') ?? 0;
                                $total = $subtotal - $discount;

                                return number_format($total, 2).' ر.س';
                            }),
                        Forms\Components\Hidden::make('subtotal')
                            ->default(0)
                            ->dehydrated(),
                        Forms\Components\Hidden::make('total')
                            ->default(0)
                            ->dehydrated(),
                    ])
                    ->columns(3),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull()
                    ->rows(3)
                    ->disabled(fn ($record) => $record && $record->isPosted()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'مؤكدة' : 'مسودة')
                    ->color(fn (string $state): string => $state === 'posted' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->formatStateUsing(fn (string $state): string => $state === 'cash' ? 'نقدي' : 'آجل')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'cash' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'posted' => 'مؤكدة',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SalesInvoice $record) {
                        try {
                            $stockService = app(StockService::class);
                            $treasuryService = app(TreasuryService::class);

                            DB::transaction(function () use ($record, $stockService, $treasuryService) {
                                // Post stock movements
                                $stockService->postSalesInvoice($record);

                                // Post treasury transactions
                                $treasuryService->postSalesInvoice($record);

                                // Update invoice status
                                $record->update(['status' => 'posted']);
                            });

                            Notification::make()
                                ->success()
                                ->title('تم تأكيد الفاتورة بنجاح')
                                ->body('تم تسجيل حركة المخزون والخزينة')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في تأكيد الفاتورة')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (SalesInvoice $record) => $record->isDraft()),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SalesInvoice $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (SalesInvoice $record) => $record->isDraft()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
        ];
    }
}
