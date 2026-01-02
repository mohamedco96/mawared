<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Resources\PurchaseInvoiceResource\RelationManagers;
use App\Models\Product;
use App\Models\PurchaseInvoice;
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

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'فواتير الشراء';

    protected static ?string $modelLabel = 'فاتورة شراء';

    protected static ?string $pluralModelLabel = 'فواتير الشراء';

    protected static ?string $navigationGroup = 'المشتريات';

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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الفاتورة')
                    ->schema([
                        Forms\Components\TextInput::make('invoice_number')
                            ->label('رقم الفاتورة')
                            ->default(fn () => 'PI-'.now()->format('Ymd').'-'.Str::random(6))
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
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('partner_id')
                            ->label('المورد')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'supplier'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('الاسم')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Hidden::make('type')
                                    ->default('supplier'),
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
                            ->createOptionModalHeading('إضافة مورد جديد')
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
                                // Auto-fill paid_amount based on payment method
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
                                    $set('paid_amount', $netTotal);
                                    $set('remaining_amount', 0);
                                } else {
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
                                                $set('unit_cost', $product->avg_cost ?: 0);
                                                $set('quantity', 1);
                                                $set('total', $product->avg_cost ?: 0);
                                            }
                                        }
                                    })
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->label('اسم المنتج')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('barcode')
                                            ->label('الباركود')
                                            ->helperText('سيتم توليده تلقائياً إذا ترك فارغاً')
                                            ->unique()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('sku')
                                            ->label('رمز المنتج')
                                            ->helperText('سيتم توليده تلقائياً إذا ترك فارغاً')
                                            ->unique()
                                            ->maxLength(255),
                                        Forms\Components\Select::make('small_unit_id')
                                            ->label('الوحدة الصغيرة (الأساسية)')
                                            ->relationship('smallUnit', 'name')
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('الاسم')
                                                    ->required(),
                                                Forms\Components\TextInput::make('symbol')
                                                    ->label('الرمز'),
                                            ])
                                            ->createOptionModalHeading('إضافة وحدة قياس جديدة'),
                                        Forms\Components\TextInput::make('retail_price')
                                            ->label('سعر التجزئة')
                                            ->numeric()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                            ->default(0)
                                            ->required()
                                            ->step(0.01),
                                        Forms\Components\TextInput::make('min_stock')
                                            ->label('الحد الأدنى للمخزون')
                                            ->numeric()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                            ->default(0)
                                            ->required(),
                                    ])
                                    ->createOptionModalHeading('إضافة منتج جديد')
                                    ->columnSpan(4)
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\Select::make('unit_type')
                                    ->label('الوحدة')
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
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
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
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
                                        $set('total', $unitCost * $state);
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
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('السعر')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->required()
                                    ->step(0.0001)
                                    ->minValue(0)
                                    ->live(debounce: 500)
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
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('new_selling_price')
                                    ->label('سعر البيع الجديد (صغير)')
                                    ->helperText('إذا تم تحديده، سيتم تحديث سعر المنتج تلقائياً')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->step(0.01)
                                    ->nullable()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $productId = $get('product_id');
                                        if ($productId && $state !== null && $state !== '') {
                                            $product = Product::find($productId);
                                            if ($product && $product->large_unit_id && $product->factor > 0) {
                                                $calculatedPrice = floatval($state) * intval($product->factor);
                                                $set('new_large_selling_price', number_format($calculatedPrice, 2, '.', ''));
                                            }
                                        }
                                    })
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('new_large_selling_price')
                                    ->label('سعر البيع الجديد (كبير)')
                                    ->helperText('يتم حسابه تلقائياً (سعر الوحدة الصغيرة × معامل التحويل)، يمكن تعديله يدوياً')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->step(0.01)
                                    ->nullable()
                                    ->visible(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return false;
                                        $product = Product::find($productId);
                                        return $product && $product->large_unit_id;
                                    })
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('wholesale_price')
                                    ->label('سعر الجملة الجديد (صغير)')
                                    ->helperText('إذا تم تحديده، سيتم تحديث سعر الجملة للمنتج تلقائياً')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->step(0.01)
                                    ->nullable()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $productId = $get('product_id');
                                        if ($productId && $state !== null && $state !== '') {
                                            $product = Product::find($productId);
                                            if ($product && $product->large_unit_id && $product->factor > 0) {
                                                $calculatedPrice = floatval($state) * intval($product->factor);
                                                $set('large_wholesale_price', number_format($calculatedPrice, 2, '.', ''));
                                            }
                                        }
                                    })
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('large_wholesale_price')
                                    ->label('سعر الجملة الجديد (كبير)')
                                    ->helperText('يتم حسابه تلقائياً (سعر الوحدة الصغيرة × معامل التحويل)، يمكن تعديله يدوياً')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->step(0.01)
                                    ->nullable()
                                    ->visible(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) return false;
                                        $product = Product::find($productId);
                                        return $product && $product->large_unit_id;
                                    })
                                    ->columnSpan(2)
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
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
                                    $items = $get('items') ?? [];

                                    // Calculate subtotal from items manually
                                    $subtotal = 0;
                                    foreach ($items as $item) {
                                        $quantity = floatval($item['quantity'] ?? 0);
                                        $unitCost = floatval($item['unit_cost'] ?? 0);
                                        $subtotal += $quantity * $unitCost;
                                    }

                                    if ($discountType === 'percentage') {
                                        if (floatval($value) > 100) {
                                            $fail('نسبة الخصم لا يمكن أن تتجاوز 100%.');
                                        }
                                    } else {
                                        // Fixed discount - validate against calculated subtotal
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
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->step(0.0001)
                            ->minValue(0)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');
                                $discountType = $get('discount_type') ?? 'fixed';
                                $discountValue = $get('discount_value') ?? 0;

                                $totalDiscount = $discountType === 'percentage'
                                    ? $subtotal * ($discountValue / 100)
                                    : $discountValue;

                                $netTotal = $subtotal - $totalDiscount;
                                $paidAmount = floatval($state ?? 0);

                                $set('remaining_amount', max(0, $netTotal - $paidAmount));
                                $set('subtotal', $subtotal);
                                $set('total', $netTotal);
                            })
                            ->rules([
                                'numeric',
                                'min:0',
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    $items = $get('items') ?? [];

                                    // Calculate subtotal from items manually
                                    $subtotal = 0;
                                    foreach ($items as $item) {
                                        $quantity = floatval($item['quantity'] ?? 0);
                                        $unitCost = floatval($item['unit_cost'] ?? 0);
                                        $subtotal += $quantity * $unitCost;
                                    }

                                    $discountType = $get('discount_type') ?? 'fixed';
                                    $discountValue = floatval($get('discount_value') ?? 0);

                                    // Calculate total discount
                                    $totalDiscount = $discountType === 'percentage'
                                        ? $subtotal * ($discountValue / 100)
                                        : $discountValue;

                                    // Calculate final total after discount
                                    $netTotal = $subtotal - $totalDiscount;
                                    $paidAmount = floatval($value);

                                    if ($paidAmount > $netTotal) {
                                        $fail('لا يمكن دفع مبلغ (' . number_format($paidAmount, 2) . ') أكبر من إجمالي الفاتورة (' . number_format($netTotal, 2) . ').');
                                    }

                                    if ($paidAmount < 0) {
                                        $fail('المبلغ المدفوع يجب أن لا يكون سالباً.');
                                    }
                                },
                            ])
                            ->validationAttribute('المبلغ المدفوع')
                            ->helperText('يتم ملؤه تلقائياً حسب طريقة الدفع أو يمكن تعديله يدوياً')
                            ->visible(fn (Get $get) => $get('payment_method') !== 'cash')
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('المبلغ المتبقي')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn (Get $get) => $get('payment_method') !== 'cash'),
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

        // Auto-fill paid_amount based on payment method
        $currentPaidAmount = floatval($get('paid_amount') ?? 0);

        // Only auto-fill if payment method is cash or if current paid amount exceeds net total
        if ($paymentMethod === 'cash') {
            $set('paid_amount', $netTotal);
            $set('remaining_amount', 0);
        } else {
            // For credit, only reset if current paid_amount exceeds net total
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
                    ->label('المورد')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->sortable(),
                Tables\Columns\TextColumn::make('returns_count')
                    ->label('المرتجعات')
                    ->counts('returns')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn (string $state): string => $state === 'draft' ? 'مسودة' : 'مؤكدة')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'draft' ? 'warning' : 'success'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('حالة الدفع')
                    ->badge()
                    ->state(function ($record): string {
                        if ($record->status === 'draft') {
                            return 'مسودة';
                        }

                        $remaining = floatval($record->remaining_amount);
                        $total = floatval($record->total);

                        if ($remaining <= 0.01) {
                            return 'مدفوع بالكامل';
                        } elseif ($remaining < $total) {
                            return 'مدفوع جزئياً';
                        } else {
                            return 'غير مدفوع';
                        }
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'مدفوع بالكامل' => 'success',
                        'مدفوع جزئياً' => 'warning',
                        'غير مدفوع' => 'danger',
                        'مسودة' => 'gray',
                        default => 'gray',
                    }),
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
                    ->label('المورد')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('has_returns')
                    ->label('لديه مرتجعات')
                    ->placeholder('الكل')
                    ->trueLabel('لديه مرتجعات')
                    ->falseLabel('ليس لديه مرتجعات')
                    ->queries(
                        true: fn ($query) => $query->has('returns'),
                        false: fn ($query) => $query->doesntHave('returns'),
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
                    ->action(function (PurchaseInvoice $record) {
                        try {
                            $stockService = app(StockService::class);
                            $treasuryService = app(TreasuryService::class);

                            DB::transaction(function () use ($record, $stockService, $treasuryService) {
                                // Post stock movements
                                $stockService->postPurchaseInvoice($record);

                                // Post treasury transactions
                                $treasuryService->postPurchaseInvoice($record);

                                // Update product prices if any new prices are set
                                foreach ($record->items as $item) {
                                    if ($item->new_selling_price !== null || $item->new_large_selling_price !== null || $item->wholesale_price !== null || $item->large_wholesale_price !== null) {
                                        $stockService->updateProductPrice(
                                            $item->product,
                                            $item->new_selling_price,
                                            $item->unit_type,
                                            $item->new_large_selling_price,
                                            $item->wholesale_price,
                                            $item->large_wholesale_price
                                        );
                                    }
                                }

                                // Update invoice status (using saveQuietly to bypass model events)
                                $record->status = 'posted';
                                $record->saveQuietly();
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
                    ->visible(fn (PurchaseInvoice $record) => $record->isDraft()),
                Tables\Actions\Action::make('add_payment')
                    ->label('تسجيل دفعة')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
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
                                    ->default(fn (PurchaseInvoice $record) => floatval($record->current_remaining))
                                    ->rules([
                                        'required',
                                        'numeric',
                                        'min:0.01',
                                        fn (PurchaseInvoice $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
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
                    ->action(function (array $data, PurchaseInvoice $record) {
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
                            ->body('تم إضافة الدفعة وتحديث رصيد المورد والخزينة')
                            ->send();
                    })
                    ->visible(fn (PurchaseInvoice $record) =>
                        $record->isPosted() &&
                        !$record->isFullyPaid()
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseInvoice $record) => $record->isDraft()),
                Tables\Actions\ReplicateAction::make()
                    ->excludeAttributes(['invoice_number', 'status'])
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->invoice_number = 'PI-'.now()->format('Ymd').'-'.\Illuminate\Support\Str::random(6);
                        $replica->status = 'draft';
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PurchaseInvoice $record) => $record->isDraft()),
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
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }
}
