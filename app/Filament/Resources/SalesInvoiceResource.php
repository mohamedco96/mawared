<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Filament\Resources\SalesInvoiceResource\RelationManagers;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Treasury;
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

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'draft')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->invoice_number;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'العميل' => $record->partner?->name,
            'الإجمالي' => number_format($record->total, 2) . ' ج.م',
            'الحالة' => $record->status === 'posted' ? 'مؤكدة' : 'مسودة',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'partner.name'];
    }

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
                            ->label('العميل')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'customer'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('الاسم')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Hidden::make('type')
                                    ->default('customer'),
                                Forms\Components\TextInput::make('phone')
                                    ->label('الهاتف')
                                    ->tel()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('gov_id')
                                    ->label('الهوية الوطنية')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('region')
                                    ->label('المنطقة')
                                    ->maxLength(255),
                            ])
                            ->createOptionModalHeading('إضافة عميل جديد')
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('payment_method')
                            ->label('طريقة الدفع')
                            ->options([
                                'cash' => 'نقدي',
                                'credit' => 'آجل',
                            ])
                            ->default('cash')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                // Calculate total for remaining_amount updates
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');
                                $discountType = $get('discount_type') ?? 'fixed';
                                $discountValue = $get('discount_value') ?? 0;

                                // Calculate discount
                                $totalDiscount = $discountType === 'percentage'
                                    ? $subtotal * ($discountValue / 100)
                                    : $discountValue;

                                $netTotal = $subtotal - $totalDiscount;

                                if ($state === 'cash') {
                                    // For cash: DO NOT set paid_amount (dehydrate handles it)
                                    // Just set remaining_amount to 0
                                    $set('remaining_amount', 0);
                                } else {
                                    // For credit: reset paid_amount and set remaining to total
                                    $set('paid_amount', 0);
                                    $set('remaining_amount', $netTotal);
                                }
                            })
                            ->native(false)
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('أصناف الفاتورة')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('الأصناف')
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
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $unitType = $get('unit_type') ?? 'small';
                                                $price = $unitType === 'large' && $product->large_retail_price
                                                    ? $product->large_retail_price
                                                    : $product->retail_price;
                                                $set('unit_price', $price);
                                                $set('quantity', 1);
                                                $set('total', $price);
                                            }
                                        }

                                        // Trigger quantity re-validation when product changes
                                        $quantity = $get('quantity');
                                        if ($quantity) {
                                            $set('quantity', $quantity);
                                        }
                                    })
                                    ->columnSpan(4)
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
                                                $set('total', $price * $quantity);
                                            }
                                        }

                                        // Trigger quantity re-validation when unit type changes
                                        $quantity = $get('quantity');
                                        if ($quantity) {
                                            $set('quantity', $quantity);
                                        }
                                    })
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->integer()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitPrice = $get('unit_price') ?? 0;
                                        $set('total', $unitPrice * $state);
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
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('السعر')
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
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->salesInvoice && $record->salesInvoice->isPosted()),
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
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                static::recalculateTotals($set, $get);
                            })
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                    ]),

                Forms\Components\Section::make('الإجماليات')
                    ->schema([
                        Forms\Components\Placeholder::make('total_items_count')
                            ->label('عدد الأصناف')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                return count($items) . ' صنف';
                            }),
                        Forms\Components\Placeholder::make('calculated_subtotal')
                            ->label('المجموع الفرعي')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');

                                return number_format($subtotal, 2);
                            }),
                        Forms\Components\Select::make('discount_type')
                            ->label('نوع الخصم')
                            ->options([
                                'fixed' => 'مبلغ ثابت',
                                'percentage' => 'نسبة مئوية',
                            ])
                            ->default('fixed')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                static::recalculateTotals($set, $get);
                            })
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\TextInput::make('discount_value')
                            ->label(function (Get $get) {
                                return $get('discount_type') === 'percentage'
                                    ? 'نسبة الخصم (%)'
                                    : 'قيمة الخصم';
                            })
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->step(0.0001)
                            ->minValue(0)
                            ->maxValue(function (Get $get) {
                                return $get('discount_type') === 'percentage' ? 100 : null;
                            })
                            ->suffix(fn (Get $get) => $get('discount_type') === 'percentage' ? '%' : '')
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                static::recalculateTotals($set, $get);
                            })
                            ->rules([
                                'numeric',
                                'min:0',
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    $discountType = $get('discount_type') ?? 'fixed';
                                    $items = $get('../../items') ?? [];
                                    $subtotal = collect($items)->sum('total');

                                    if ($discountType === 'percentage') {
                                        if (floatval($value) > 100) {
                                            $fail('نسبة الخصم لا يمكن أن تتجاوز 100%.');
                                        }
                                    } else {
                                        // Fixed discount
                                        if (floatval($value) > $subtotal) {
                                            $fail('قيمة الخصم (' . number_format($value, 2) . ') لا يمكن أن تتجاوز المجموع الفرعي (' . number_format($subtotal, 2) . ').');
                                        }
                                    }
                                },
                            ])
                            ->validationAttribute('قيمة الخصم')
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Placeholder::make('calculated_discount_display')
                            ->label('الخصم المحسوب')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');
                                $discountType = $get('discount_type') ?? 'fixed';
                                $discountValue = floatval($get('discount_value') ?? 0);

                                $totalDiscount = $discountType === 'percentage'
                                    ? $subtotal * ($discountValue / 100)
                                    : $discountValue;

                                return number_format($totalDiscount, 2);
                            }),
                        Forms\Components\Placeholder::make('calculated_total')
                            ->label('الإجمالي النهائي')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');
                                $discountType = $get('discount_type') ?? 'fixed';
                                $discountValue = floatval($get('discount_value') ?? 0);

                                $totalDiscount = $discountType === 'percentage'
                                    ? $subtotal * ($discountValue / 100)
                                    : $discountValue;

                                $total = $subtotal - $totalDiscount;

                                return number_format($total, 2);
                            }),
                        // Input for CREDIT (Editable Down Payment)
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('المبلغ المدفوع (مقدم)')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->step(0.0001)
                            ->minValue(0)
                            // A. VISIBILITY: Only show for Credit
                            ->visible(fn (Get $get) => $get('payment_method') === 'credit')
                            // B. DEHYDRATION MAGIC: If Cash, save Total. If Credit, save User Input.
                            ->dehydrated(true)
                            ->dehydrateStateUsing(function ($state, Get $get) {
                                if ($get('payment_method') === 'cash') {
                                    return floatval($get('total'));
                                }
                                return floatval($state);
                            })
                            // C. REACTIVITY: Only needed for updating remaining_amount in Credit mode
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $total = floatval($get('total'));
                                $paid = floatval($state);
                                $set('remaining_amount', max(0, $total - $paid));
                            })
                            ->rules([
                                'numeric',
                                'min:0',
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($get('payment_method') === 'credit') {
                                        $total = floatval($get('total'));
                                        if ($value > $total) {
                                            $fail('لا يمكن دفع مبلغ أكبر من إجمالي الفاتورة.');
                                        }
                                    }
                                },
                            ])
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('المبلغ المتبقي')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn (Get $get) => $get('payment_method') === 'credit'),
                        Forms\Components\Hidden::make('subtotal')
                            ->default(0)
                            ->dehydrated(),
                        Forms\Components\Hidden::make('total')
                            ->default(0)
                            ->dehydrated(),
                        Forms\Components\Hidden::make('discount')
                            ->default(0)
                            ->dehydrated(),
                    ])
                    ->columns(3),

                // Installment Plan Section
                Forms\Components\Section::make('خطة التقسيط')
                    ->schema([
                        Forms\Components\Toggle::make('has_installment_plan')
                            ->label('تقسيط المبلغ المتبقي')
                            ->helperText('تفعيل نظام الأقساط للمبلغ المتبقي بعد الدفعة الأولى')
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    $set('installment_months', null);
                                    $set('installment_start_date', null);
                                    $set('installment_notes', null);
                                }
                            }),

                        Forms\Components\TextInput::make('installment_months')
                            ->label('عدد الأقساط')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(120) // Max 10 years
                            ->default(3)
                            ->required()
                            ->visible(fn (Get $get) => $get('has_installment_plan'))
                            ->helperText('عدد الأقساط الشهرية'),

                        Forms\Components\DatePicker::make('installment_start_date')
                            ->label('تاريخ أول قسط')
                            ->required()
                            ->visible(fn (Get $get) => $get('has_installment_plan'))
                            ->default(now()->addMonth()->startOfMonth()) // Default to next month
                            ->helperText('تاريخ استحقاق القسط الأول'),

                        Forms\Components\Textarea::make('installment_notes')
                            ->label('ملاحظات التقسيط')
                            ->visible(fn (Get $get) => $get('has_installment_plan'))
                            ->rows(2),
                    ])
                    ->visible(fn (Get $get) => $get('payment_method') === 'credit')
                    ->collapsible()
                    ->collapsed(false),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull()
                    ->rows(3)
                    ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
            ]);
    }

    /**
     * Recalculate all totals when items or discount changes
     */
    protected static function recalculateTotals(Set $set, Get $get): void
    {
        $items = $get('items') ?? [];
        $subtotal = collect($items)->sum('total');
        $discountType = $get('discount_type') ?? 'fixed';
        $discountValue = floatval($get('discount_value') ?? 0);
        $paymentMethod = $get('payment_method') ?? 'cash';

        // Calculate discount
        $totalDiscount = $discountType === 'percentage'
            ? $subtotal * ($discountValue / 100)
            : $discountValue;

        $netTotal = $subtotal - $totalDiscount;

        // Update hidden fields
        $set('subtotal', $subtotal);
        $set('discount', $totalDiscount); // OLD field for backward compatibility
        $set('total', $netTotal);

        // Handle remaining_amount based on payment method
        if ($paymentMethod === 'cash') {
            // For cash: DO NOTHING to paid_amount (dehydrate logic handles saving)
            // Just set remaining_amount to 0 (cash payments are always full)
            $set('remaining_amount', 0);
        } else {
            // For credit: update remaining_amount based on current paid_amount
            $currentPaidAmount = floatval($get('paid_amount') ?? 0);

            // Reset if current paid_amount exceeds net total
            if ($currentPaidAmount > $netTotal) {
                $set('paid_amount', 0);
                $set('remaining_amount', $netTotal);
            } else {
                $set('remaining_amount', max(0, $netTotal - $currentPaidAmount));
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['partner', 'warehouse', 'creator'])->withSum('payments', 'amount'))
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
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options([
                        'cash' => 'نقدي',
                        'credit' => 'آجل',
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('print')
                    ->label('طباعة PDF')
                    ->icon('heroicon-o-printer')
                    ->url(fn (SalesInvoice $record) => route('invoices.sales.print', $record))
                    ->openUrlInNewTab()
                    ->visible(fn (SalesInvoice $record) => $record->isPosted())
                    ->color('success'),
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
                Tables\Actions\Action::make('add_payment')
                    ->label('تسجيل دفعة')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->modalHeading('تسجيل دفعة جديدة')
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('المبلغ')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.01)
                                    ->suffix('ج.م')
                                    ->step(0.01)
                                    ->default(fn (SalesInvoice $record) => floatval($record->current_remaining))
                                    ->rules([
                                        'required',
                                        'numeric',
                                        'min:0.01',
                                        fn (SalesInvoice $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
                                            $remainingAmount = floatval($record->current_remaining);
                                            if (floatval($value) > $remainingAmount) {
                                                $fail('لا يمكن دفع مبلغ (' . number_format($value, 2) . ' ج.م) أكبر من المبلغ المتبقي (' . number_format($remainingAmount, 2) . ' ج.م).');
                                            }
                                        },
                                    ]),

                                Forms\Components\DatePicker::make('payment_date')
                                    ->label('تاريخ الدفع')
                                    ->required()
                                    ->default(now())
                                    ->maxDate(now()),

                                Forms\Components\TextInput::make('discount')
                                    ->label('خصم التسوية')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->suffix('ج.م')
                                    ->step(0.01),

                                Forms\Components\Select::make('treasury_id')
                                    ->label('الخزينة')
                                    ->options(Treasury::pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->default(fn () => Treasury::where('type', 'cash')->first()?->id ?? Treasury::first()?->id),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, SalesInvoice $record) {
                        $treasuryService = app(TreasuryService::class);

                        $treasuryService->recordInvoicePayment(
                            $record,
                            floatval($data['amount']),
                            floatval($data['discount'] ?? 0),
                            $data['treasury_id'],
                            $data['notes'] ?? null
                        );

                        Notification::make()
                            ->success()
                            ->title('تم تسجيل الدفعة بنجاح')
                            ->body('تم إضافة الدفعة وتحديث رصيد العميل والخزينة')
                            ->send();
                    })
                    ->visible(fn (SalesInvoice $record) =>
                        $record->isPosted() &&
                        !$record->isFullyPaid()
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(fn (SalesInvoice $record) => $record->isDraft()),
                Tables\Actions\ReplicateAction::make()
                    ->excludeAttributes(['invoice_number', 'status'])
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->invoice_number = 'SI-'.now()->format('Ymd').'-'.\Illuminate\Support\Str::random(6);
                        $replica->status = 'draft';
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (SalesInvoice $record) => $record->isDraft()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            foreach ($records as $record) {
                                if ($record->stockMovements()->exists() ||
                                    $record->treasuryTransactions()->exists() ||
                                    $record->payments()->exists()) {
                                    Notification::make()
                                        ->danger()
                                        ->title('خطأ في الحذف')
                                        ->body("الفاتورة {$record->invoice_number} لديها حركات مرتبطة ولا يمكن حذفها")
                                        ->send();

                                    throw new \Filament\Notifications\HaltActionException();
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesInvoices::route('/'),
            'create' => Pages\CreateSalesInvoice::route('/create'),
            'view' => Pages\ViewSalesInvoice::route('/{record}'),
            'edit' => Pages\EditSalesInvoice::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
            RelationManagers\InstallmentsRelationManager::class,
        ];
    }
}
