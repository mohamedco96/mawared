<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesReturnResource\Pages;
use App\Models\Product;
use App\Models\SalesReturn;
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

class SalesReturnResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'مرتجعات المبيعات';

    protected static ?string $modelLabel = 'مرتجع مبيعات';

    protected static ?string $pluralModelLabel = 'مرتجعات المبيعات';

    protected static ?string $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المرتجع')
                    ->schema([
                        Forms\Components\TextInput::make('return_number')
                            ->label('رقم المرتجع')
                            ->default(fn () => 'RET-SALE-'.now()->format('Ymd').'-'.Str::random(6))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'draft' => 'مسودة',
                                'posted' => 'مؤكدة',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false)
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($value === 'posted') {
                                        $items = $get('items');
                                        if (empty($items)) {
                                            $fail('لا يمكن تأكيد المرتجع بدون أصناف.');
                                        }
                                    }
                                },
                            ])
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('المخزن')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('partner_id')
                            ->label('العميل')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'customer'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('sales_invoice_id', null);
                                $set('items', []);
                            })
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('sales_invoice_id')
                            ->label('فاتورة البيع')
                            ->relationship('salesInvoice', 'invoice_number',
                                fn ($query, Get $get) => $query
                                    ->where('partner_id', $get('partner_id'))
                                    ->where('status', 'posted')
                            )
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $invoice = \App\Models\SalesInvoice::with('items.product')->find($state);
                                    if ($invoice) {
                                        $items = $invoice->items->map(function ($item) {
                                            return [
                                                'product_id' => $item->product_id,
                                                'unit_type' => $item->unit_type,
                                                'quantity' => 1,
                                                'unit_price' => $item->net_unit_price,
                                                'discount' => 0,
                                                'total' => $item->net_unit_price,
                                                'max_quantity' => $item->quantity,
                                            ];
                                        })->toArray();
                                        $set('items', $items);
                                    }
                                } else {
                                    $set('items', []);
                                }
                            })
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord)
                            ->visible(fn (Get $get) => $get('partner_id') !== null)
                            ->helperText('اختياري: اختر فاتورة لتحميل أصنافها تلقائياً'),
                        Forms\Components\Hidden::make('payment_method')
                            ->default('cash')
                            ->dehydrated(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('أصناف المرتجع')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->addActionLabel('إضافة صنف')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable(['name', 'barcode', 'sku'])
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get, $record) {
                                        if ($state && !$get('../../sales_invoice_id')) {
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
                                    ->disabled(fn ($record, Get $get, $livewire) =>
                                        ($record && $record->salesReturn && $record->salesReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord) ||
                                        $get('../../sales_invoice_id') !== null
                                    )
                                    ->dehydrated()
                                    ->columnSpan(4),
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
                                                $set('total', $price * $quantity);
                                            }
                                        }
                                    })
                                    ->disabled(fn ($record, $livewire) => $record && $record->salesReturn && $record->salesReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->integer()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $set('total', $unitPrice * $state);
                                    })
                                    ->rules([
                                        'required',
                                        'integer',
                                        'min:1',
                                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                            if ($value !== null && intval($value) <= 0) {
                                                $fail('الكمية يجب أن تكون أكبر من صفر.');
                                            }
                                        },
                                    ])
                                    ->validationAttribute('الكمية')
                                    ->disabled(fn ($record, $livewire) => $record && $record->salesReturn && $record->salesReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->required()
                                    ->step(0.0001)
                                    ->minValue(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total', $state * $quantity);
                                    })
                                    ->rules([
                                        'required',
                                        'numeric',
                                        'min:0',
                                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                            if ($value !== null && floatval($value) < 0) {
                                                $fail('سعر الوحدة يجب أن لا يكون سالباً.');
                                            }
                                        },
                                    ])
                                    ->validationAttribute('سعر الوحدة')
                                    ->disabled(fn ($record, Get $get, $livewire) =>
                                        ($record && $record->salesReturn && $record->salesReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord) ||
                                        $get('../../sales_invoice_id') !== null
                                    )
                                    ->dehydrated()
                                    ->columnSpan(2),
                                Forms\Components\Hidden::make('discount')
                                    ->default(0)
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['product_id'] ? Product::find($state['product_id'])?->name : null)
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
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

                                return number_format($subtotal, 2);
                            }),
                        Forms\Components\TextInput::make('discount')
                            ->label('إجمالي الخصم')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->step(0.01)
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
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Placeholder::make('calculated_total')
                            ->label('الإجمالي النهائي')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $subtotal += $item['total'] ?? 0;
                                }
                                $discount = floatval($get('discount') ?? 0);
                                $total = $subtotal - $discount;

                                return number_format($total, 2);
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
                    ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('return_number')
                    ->label('رقم المرتجع')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salesInvoice.invoice_number')
                    ->label('فاتورة البيع')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info')
                    ->default('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'مؤكدة' : 'مسودة')
                    ->color(fn (string $state): string => $state === 'posted' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 2)
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
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('العميل')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('sales_invoice_id')
                    ->label('مرتبط بفاتورة')
                    ->placeholder('الكل')
                    ->trueLabel('مرتبط بفاتورة')
                    ->falseLabel('غير مرتبط بفاتورة')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('sales_invoice_id'),
                        false: fn ($query) => $query->whereNull('sales_invoice_id'),
                    ),
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
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\Filter::make('total')
                    ->label('الإجمالي')
                    ->form([
                        Forms\Components\TextInput::make('from')
                            ->label('من')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01),
                        Forms\Components\TextInput::make('until')
                            ->label('إلى')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $amount) => $q->where('total', '>=', $amount))
                            ->when($data['until'], fn ($q, $amount) => $q->where('total', '<=', $amount));
                    }),
                Tables\Filters\SelectFilter::make('created_by')
                    ->label('المستخدم')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SalesReturn $record) {
                        // Validate return has items
                        if ($record->items()->count() === 0) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن تأكيد المرتجع')
                                ->body('المرتجع لا يحتوي على أي أصناف')
                                ->send();
                            return;
                        }

                        try {
                            $stockService = app(StockService::class);
                            $treasuryService = app(TreasuryService::class);

                            DB::transaction(function () use ($record, $stockService, $treasuryService) {
                                // Post stock movements
                                $stockService->postSalesReturn($record);

                                // Post treasury transactions
                                $treasuryService->postSalesReturn($record);

                                // Update return status
                                $record->update(['status' => 'posted']);
                            });

                            Notification::make()
                                ->success()
                                ->title('تم تأكيد المرتجع بنجاح')
                                ->body('تم تسجيل حركة المخزون والخزينة')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في تأكيد المرتجع')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (SalesReturn $record) => $record->isDraft()),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SalesReturn $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (SalesReturn $record) => $record->isDraft()),
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
            'index' => Pages\ListSalesReturns::route('/'),
            'create' => Pages\CreateSalesReturn::route('/create'),
            'edit' => Pages\EditSalesReturn::route('/{record}/edit'),
        ];
    }
}
