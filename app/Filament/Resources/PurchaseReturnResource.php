<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Models\Product;
use App\Models\PurchaseReturn;
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

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'مرتجعات المشتريات';

    protected static ?string $modelLabel = 'مرتجع مشتريات';

    protected static ?string $pluralModelLabel = 'مرتجعات المشتريات';

    protected static ?string $navigationGroup = 'المشتريات';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المرتجع')
                    ->schema([
                        Forms\Components\TextInput::make('return_number')
                            ->label('رقم المرتجع')
                            ->default(fn () => 'RET-PURCHASE-'.now()->format('Ymd').'-'.Str::random(6))
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
                            ->label('المورد')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'supplier'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                $set('purchase_invoice_id', null);
                                $set('items', []);
                            })
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('purchase_invoice_id')
                            ->label('فاتورة الشراء')
                            ->relationship('purchaseInvoice', 'invoice_number',
                                fn ($query, Get $get) => $query
                                    ->where('partner_id', $get('partner_id'))
                                    ->where('status', 'posted')
                            )
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $invoice = \App\Models\PurchaseInvoice::with('items.product')->find($state);
                                    if ($invoice) {
                                        $items = $invoice->items->map(function ($item) {
                                            return [
                                                'product_id' => $item->product_id,
                                                'unit_type' => $item->unit_type,
                                                'quantity' => 1,
                                                'unit_cost' => $item->net_unit_cost,
                                                'discount' => 0,
                                                'total' => $item->net_unit_cost,
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
                                        if ($state && !$get('../../purchase_invoice_id')) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $set('unit_cost', $product->avg_cost);
                                                $set('quantity', 1);
                                                $set('discount', 0);
                                                $set('total', $product->avg_cost);
                                            }
                                        }

                                        // Trigger quantity re-validation when product changes
                                        $quantity = $get('quantity');
                                        if ($quantity) {
                                            $set('quantity', $quantity);
                                        }
                                    })
                                    ->disabled(fn ($record, Get $get, $livewire) =>
                                        ($record && $record->purchaseReturn && $record->purchaseReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord) ||
                                        $get('../../purchase_invoice_id') !== null
                                    )
                                    ->dehydrated(),
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
                                        // Trigger quantity re-validation when unit type changes
                                        $quantity = $get('quantity');
                                        if ($quantity) {
                                            $set('quantity', $quantity);
                                        }
                                    })
                                    ->disabled(fn ($record, $livewire) => $record && $record->purchaseReturn && $record->purchaseReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->integer()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitCost = $get('unit_cost') ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($unitCost * $state) - $discount);
                                    })
                                    ->rules([
                                        'required',
                                        'integer',
                                        'min:1',
                                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            // Validate positive quantity
                                            if ($value !== null && intval($value) <= 0) {
                                                $fail('الكمية يجب أن تكون أكبر من صفر.');
                                                return;
                                            }

                                            $productId = $get('product_id');
                                            $warehouseId = $get('../../warehouse_id');
                                            $unitType = $get('unit_type') ?? 'small';

                                            if (!$productId || !$warehouseId || !$value) {
                                                return;
                                            }

                                            $product = \App\Models\Product::find($productId);
                                            if (!$product) {
                                                return;
                                            }

                                            $stockService = app(\App\Services\StockService::class);
                                            $baseQuantity = $stockService->convertToBaseUnit($product, intval($value), $unitType);

                                            $validation = $stockService->getStockValidationMessage(
                                                $warehouseId,
                                                $productId,
                                                $baseQuantity,
                                                $unitType
                                            );

                                            if (!$validation['is_available']) {
                                                $fail($validation['message']);
                                            }
                                        },
                                    ])
                                    ->validationAttribute('الكمية')
                                    ->disabled(fn ($record, $livewire) => $record && $record->purchaseReturn && $record->purchaseReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('التكلفة')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->required()
                                    ->step(0.0001)
                                    ->minValue(0)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($state * $quantity) - $discount);
                                    })
                                    ->rules([
                                        'required',
                                        'numeric',
                                        'min:0',
                                        fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                            if ($value !== null && floatval($value) < 0) {
                                                $fail('تكلفة الوحدة يجب أن لا تكون سالبة.');
                                            }
                                        },
                                    ])
                                    ->validationAttribute('تكلفة الوحدة')
                                    ->disabled(fn ($record, Get $get, $livewire) =>
                                        ($record && $record->purchaseReturn && $record->purchaseReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord) ||
                                        $get('../../purchase_invoice_id') !== null
                                    )
                                    ->dehydrated(),
                                Forms\Components\TextInput::make('discount')
                                    ->label('الخصم')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->default(0)
                                    ->step(0.01)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitCost = $get('unit_cost') ?? 0;
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total', ($unitCost * $quantity) - $state);
                                    })
                                    ->disabled(fn ($record, Get $get, $livewire) =>
                                        ($record && $record->purchaseReturn && $record->purchaseReturn->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord) ||
                                        $get('../../purchase_invoice_id') !== null
                                    )
                                    ->dehydrated()
                                    ->helperText(fn (Get $get) =>
                                        $get('../../purchase_invoice_id') !== null
                                            ? 'الخصم محسوب مسبقاً في تكلفة الوحدة من الفاتورة الأصلية'
                                            : null
                                    ),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(6)
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
                    ->label('المورد')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('purchaseInvoice.invoice_number')
                    ->label('فاتورة الشراء')
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
                    ->label('المورد')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('purchase_invoice_id')
                    ->label('مرتبط بفاتورة')
                    ->placeholder('الكل')
                    ->trueLabel('مرتبط بفاتورة')
                    ->falseLabel('غير مرتبط بفاتورة')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('purchase_invoice_id'),
                        false: fn ($query) => $query->whereNull('purchase_invoice_id'),
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
                    ->action(function (PurchaseReturn $record) {
                        try {
                            $stockService = app(StockService::class);
                            $treasuryService = app(TreasuryService::class);

                            DB::transaction(function () use ($record, $stockService, $treasuryService) {
                                // Post stock movements
                                $stockService->postPurchaseReturn($record);

                                // Post treasury transactions
                                $treasuryService->postPurchaseReturn($record);

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
                    ->visible(fn (PurchaseReturn $record) => $record->isDraft()),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseReturn $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PurchaseReturn $record) => $record->isDraft()),
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
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'edit' => Pages\EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}
