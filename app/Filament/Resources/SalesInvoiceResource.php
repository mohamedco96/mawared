<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesInvoiceResource\Pages;
use App\Filament\Resources\SalesInvoiceResource\RelationManagers;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Models\Warehouse;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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
            'الإجمالي' => number_format($record->total, 2),
            'الحالة' => $record->status === 'posted' ? 'مؤكدة' : 'مسودة',
        ];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('partner');
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
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($value === 'posted') {
                                        $items = $get('items');
                                        if (empty($items)) {
                                            $fail('لا يمكن تأكيد الفاتورة بدون أصناف.');
                                        }
                                    }
                                },
                            ])
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('المخزن')
                            ->relationship('warehouse', 'name', fn ($query) => $query->where('is_active', true))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->default(fn () => Warehouse::where('is_active', true)->first()?->id ?? Warehouse::first()?->id)
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\Select::make('partner_id')
                            ->label('العميل (فلوس لينا عنده)')
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
                        Forms\Components\Select::make('sales_person_id')
                            ->label('مندوب المبيعات')
                            ->relationship('salesperson', 'name')
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                        Forms\Components\TextInput::make('commission_rate')
                            ->label('نسبة العمولة (%)')
                            ->numeric()
                            ->suffix('%')
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(1)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $total = floatval($get('total') ?? 0);
                                $rate = floatval($state ?? 0) / 100;
                                $set('commission_amount', $total * $rate);
                            })
                            ->visible(fn (Get $get) => $get('sales_person_id') !== null)
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
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('المنتج')
                                    ->required()
                                    ->searchable()
                                    ->getSearchResultsUsing(function (?string $search, Get $get): array {
                                        $warehouseId = $get('../../warehouse_id');

                                        $query = Product::query();

                                        if (! empty($search)) {
                                            $query->where(function ($q) use ($search) {
                                                $q->where('name', 'like', "%{$search}%")
                                                    ->orWhere('sku', 'like', "%{$search}%")
                                                    ->orWhere('barcode', 'like', "%{$search}%");
                                            });
                                        } else {
                                            // Load latest products when no search
                                            $query->latest();
                                        }

                                        if ($warehouseId) {
                                            $query->withSum([
                                                'stockMovements' => fn ($q) => $q->where('warehouse_id', $warehouseId),
                                            ], 'quantity');
                                        }

                                        return $query->limit(10)
                                            ->get()
                                            ->mapWithKeys(function ($product) use ($warehouseId) {
                                                $stock = $warehouseId ? ($product->stock_movements_sum_quantity ?? 0) : 0;

                                                // Color indicators based on stock level
                                                $emoji = match (true) {
                                                    ! $warehouseId => '⚠️',
                                                    $stock <= 0 => '🔴',
                                                    $stock <= ($product->min_stock ?? 0) => '🟡',
                                                    default => '🟢'
                                                };

                                                $label = $warehouseId
                                                    ? "{$product->name} {$emoji} (متوفر: ".number_format($stock, 2).')'
                                                    : "{$product->name} {$emoji}";

                                                return [$product->id => $label];
                                            })
                                            ->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value): string {
                                        $product = Product::find($value);

                                        return $product ? $product->name : '';
                                    })
                                    ->loadingMessage('جاري التحميل...')
                                    ->searchPrompt('ابحث عن منتج بالاسم أو الباركود أو SKU')
                                    ->noSearchResultsMessage('لم يتم العثور على منتجات')
                                    ->searchingMessage('جاري البحث...')
                                    ->allowHtml()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get, $record) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $unitType = $get('unit_type') ?? 'small';
                                                $price = $unitType === 'large' && $product->large_wholesale_price
                                                    ? $product->large_wholesale_price
                                                    : $product->wholesale_price;
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
                                    ->hint(function (Get $get) {
                                        $productId = $get('product_id');

                                        if (! $productId) {
                                            return null;
                                        }

                                        $warehouseId = $get('../../warehouse_id');
                                        if (! $warehouseId) {
                                            return '⚠️ اختر المخزن أولاً';
                                        }

                                        $product = Product::find($productId);
                                        if (! $product) {
                                            return null;
                                        }

                                        $stockService = app(\App\Services\StockService::class);
                                        $baseStock = $stockService->getCurrentStock($warehouseId, $productId);

                                        // Show both units if large unit exists
                                        $smallStock = $baseStock;
                                        $largeStock = $product->large_unit_id ? floor($baseStock / $product->factor) : null;

                                        $display = "📦 المخزون: {$smallStock} {$product->smallUnit->name}";
                                        if ($largeStock !== null && $product->largeUnit) {
                                            $display .= " ({$largeStock} {$product->largeUnit->name})";
                                        }

                                        return $display;
                                    })
                                    ->hintColor(function (Get $get) {
                                        $productId = $get('product_id');
                                        $warehouseId = $get('../../warehouse_id');

                                        if (! $productId) {
                                            return null;
                                        }

                                        if (! $warehouseId) {
                                            return 'warning';
                                        }

                                        $product = Product::find($productId);
                                        if (! $product) {
                                            return null;
                                        }

                                        $stockService = app(\App\Services\StockService::class);
                                        $stock = $stockService->getCurrentStock($warehouseId, $productId);

                                        return match (true) {
                                            $stock <= 0 => 'danger',
                                            $stock <= ($product->min_stock ?? 0) => 'warning',
                                            default => 'success'
                                        };
                                    })
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
                                                $price = $state === 'large' && $product->large_wholesale_price
                                                    ? $product->large_wholesale_price
                                                    : $product->wholesale_price;
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
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->integer()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->helperText(function (Get $get) {
                                        $productId = $get('product_id');
                                        $warehouseId = $get('../../warehouse_id');
                                        $unitType = $get('unit_type') ?? 'small';

                                        if (! $productId || ! $warehouseId) {
                                            return 'أدخل الكمية';
                                        }

                                        $stockService = app(\App\Services\StockService::class);
                                        $validation = $stockService->getStockValidationMessage(
                                            $warehouseId,
                                            $productId,
                                            0, // Just for display
                                            $unitType
                                        );

                                        return "المخزون المتاح: {$validation['display_stock']}";
                                    })
                                    ->live(onBlur: true)
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

                                            if (! $productId || ! $warehouseId || ! $value) {
                                                return;
                                            }

                                            $product = \App\Models\Product::find($productId);
                                            if (! $product) {
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

                                            if (! $validation['is_available']) {
                                                $fail($validation['message']);
                                            }
                                        },
                                    ])
                                    ->validationAttribute('الكمية')
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('السعر')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->required()
                                    ->step(0.0001)
                                    ->minValue(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total', $state * $quantity);
                                    })
                                    ->helperText(function (Get $get) {
                                        // Security: Check permission
                                        if (! auth()->user()->can('view_cost_price')) {
                                            return null;
                                        }

                                        $productId = $get('product_id');
                                        if (! $productId) {
                                            return null;
                                        }

                                        // Get last purchase for this product
                                        $lastPurchase = \App\Models\PurchaseInvoiceItem::with(['purchaseInvoice.partner'])
                                            ->where('product_id', $productId)
                                            ->whereHas('purchaseInvoice', function ($query) {
                                                $query->where('status', 'posted');
                                            })
                                            ->latest('created_at')
                                            ->first();

                                        if (! $lastPurchase) {
                                            return 'لا توجد سجلات شراء';
                                        }

                                        $lastCost = number_format($lastPurchase->unit_cost, 2);
                                        $supplierName = $lastPurchase->purchaseInvoice->partner->name ?? 'غير محدد';

                                        return "💡 آخر تكلفة: {$lastCost} (المورد: {$supplierName})";
                                    })
                                    ->suffixAction(
                                        Forms\Components\Actions\Action::make('view_history')
                                            ->icon('heroicon-m-information-circle')
                                            ->tooltip('عرض سجل السعر')
                                            ->modalHeading('سجل أسعار المنتج')
                                            ->modalWidth('3xl')
                                            ->modalContent(function (Get $get) {
                                                $productId = $get('product_id');
                                                if (! $productId) {
                                                    return view('filament.components.empty-state', [
                                                        'message' => 'يرجى اختيار منتج أولاً',
                                                    ]);
                                                }

                                                $product = \App\Models\Product::find($productId);
                                                if (! $product) {
                                                    return view('filament.components.empty-state', [
                                                        'message' => 'المنتج غير موجود',
                                                    ]);
                                                }

                                                // Get last 5 purchases
                                                $purchases = \App\Models\PurchaseInvoiceItem::with(['purchaseInvoice.partner'])
                                                    ->where('product_id', $productId)
                                                    ->whereHas('purchaseInvoice', function ($query) {
                                                        $query->where('status', 'posted');
                                                    })
                                                    ->latest('created_at')
                                                    ->limit(5)
                                                    ->get();

                                                // Get last 5 sales
                                                $sales = \App\Models\SalesInvoiceItem::with(['salesInvoice.partner'])
                                                    ->where('product_id', $productId)
                                                    ->whereHas('salesInvoice', function ($query) {
                                                        $query->where('status', 'posted');
                                                    })
                                                    ->latest('created_at')
                                                    ->limit(5)
                                                    ->get();

                                                return view('filament.components.product-history', [
                                                    'product' => $product,
                                                    'purchases' => $purchases,
                                                    'sales' => $sales,
                                                    'canViewCost' => auth()->user()->can('view_cost_price'),
                                                ]);
                                            })
                                            ->visible(fn (Get $get) => $get('product_id') !== null)
                                    )
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
                                    ->columnSpan(2),
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

                                return count($items).' صنف';
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
                            ->dehydrateStateUsing(fn ($state) => $state ?? 0)
                            ->step(0.0001)
                            ->minValue(0)
                            ->maxValue(function (Get $get) {
                                return $get('discount_type') === 'percentage' ? 100 : null;
                            })
                            ->suffix(fn (Get $get) => $get('discount_type') === 'percentage' ? '%' : '')
                            ->live(onBlur: true)
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
                                    $subtotal = collect($items)->sum('total');

                                    if ($discountType === 'percentage') {
                                        if (floatval($value) > 100) {
                                            $fail('نسبة الخصم لا يمكن أن تتجاوز 100%.');
                                        }
                                    } else {
                                        // Fixed discount
                                        if (floatval($value) > $subtotal) {
                                            $fail('قيمة الخصم ('.number_format($value, 2).') لا يمكن أن تتجاوز المجموع الفرعي ('.number_format($subtotal, 2).').');
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
                        Forms\Components\Placeholder::make('calculated_commission')
                            ->label('قيمة العمولة')
                            ->content(function (Get $get) {
                                if (! $get('sales_person_id')) {
                                    return '—';
                                }
                                $total = floatval($get('total') ?? 0);
                                $rate = floatval($get('commission_rate') ?? 0) / 100;
                                $commission = $total * $rate;

                                return number_format($commission, 2).' ج.م';
                            })
                            ->visible(fn (Get $get) => $get('sales_person_id') !== null)
                            ->extraAttributes(['style' => 'color: #f59e0b; font-weight: bold;']),
                        Forms\Components\Hidden::make('commission_amount')
                            ->default(0)
                            ->dehydrated(),
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
                            ->live(onBlur: true)
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
                            ->label('المبلغ المتبقي (فلوس لينا عنده)')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->visible(fn (Get $get) => $get('payment_method') === 'credit'),
                        Forms\Components\Placeholder::make('calculated_profit')
                            ->label('إجمالي الربح')
                            ->content(function (Get $get) {
                                if (! auth()->user()->can('view_profit')) {
                                    return '—';
                                }

                                $totalRevenue = 0;
                                $totalCost = 0;
                                $items = $get('items') ?? [];

                                // Optimize: Batch load products to avoid N+1
                                $productIds = collect($items)->pluck('product_id')->filter()->unique()->toArray();
                                if (empty($productIds)) {
                                    return number_format(0, 2);
                                }
                                
                                $products = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');

                                foreach ($items as $item) {
                                    if (! isset($item['product_id'], $item['quantity'])) {
                                        continue;
                                    }

                                    $product = $products->get($item['product_id']);
                                    if (! $product) {
                                        continue;
                                    }

                                    $quantity = intval($item['quantity']);
                                    $unitType = $item['unit_type'] ?? 'small';
                                    $itemTotal = floatval($item['total'] ?? 0);

                                    // Convert to base unit
                                    $baseQuantity = $unitType === 'large' && $product->factor
                                        ? $quantity * $product->factor
                                        : $quantity;

                                    // Use avg_cost (before posting)
                                    $costPerUnit = floatval($product->avg_cost ?? 0);
                                    $totalCost += $costPerUnit * $baseQuantity;
                                    $totalRevenue += $itemTotal;
                                }

                                // Apply discount to revenue
                                $discountType = $get('discount_type') ?? 'fixed';
                                $discountValue = floatval($get('discount_value') ?? 0);
                                $discount = $discountType === 'percentage'
                                    ? $totalRevenue * ($discountValue / 100)
                                    : $discountValue;

                                $netRevenue = $totalRevenue - $discount;
                                $totalProfit = $netRevenue - $totalCost;

                                return number_format($totalProfit, 2).'';
                            })
                            ->extraAttributes(function (Get $get) {
                                if (! auth()->user()->can('view_profit')) {
                                    return [];
                                }

                                // Calculate profit for color coding (same as above with discount)
                                $totalRevenue = 0;
                                $totalCost = 0;
                                $items = $get('items') ?? [];

                                // Optimize: Batch load products to avoid N+1
                                $productIds = collect($items)->pluck('product_id')->filter()->unique()->toArray();
                                if (empty($productIds)) {
                                     return ['style' => 'color: rgb(239, 68, 68); font-weight: bold; font-size: 1.125rem;'];
                                }
                                
                                $products = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');

                                foreach ($items as $item) {
                                    if (! isset($item['product_id'], $item['quantity'])) {
                                        continue;
                                    }

                                    $product = $products->get($item['product_id']);
                                    if (! $product) {
                                        continue;
                                    }

                                    $quantity = intval($item['quantity']);
                                    $unitType = $item['unit_type'] ?? 'small';
                                    $itemTotal = floatval($item['total'] ?? 0);

                                    $baseQuantity = $unitType === 'large' && $product->factor
                                        ? $quantity * $product->factor
                                        : $quantity;

                                    $costPerUnit = floatval($product->avg_cost ?? 0);
                                    $totalCost += $costPerUnit * $baseQuantity;
                                    $totalRevenue += $itemTotal;
                                }

                                // Apply discount
                                $discountType = $get('discount_type') ?? 'fixed';
                                $discountValue = floatval($get('discount_value') ?? 0);
                                $discount = $discountType === 'percentage'
                                    ? $totalRevenue * ($discountValue / 100)
                                    : $discountValue;

                                $netRevenue = $totalRevenue - $discount;
                                $totalProfit = $netRevenue - $totalCost;
                                $marginPct = $netRevenue > 0 ? ($totalProfit / $netRevenue) * 100 : 0;

                                // Color coding thresholds
                                $color = match (true) {
                                    $marginPct >= 25 => 'rgb(34, 197, 94)', // green - excellent
                                    $marginPct >= 15 => 'rgb(234, 179, 8)', // yellow - good
                                    default => 'rgb(239, 68, 68)', // red - low
                                };

                                return [
                                    'style' => "color: {$color}; font-weight: bold; font-size: 1.125rem;",
                                ];
                            })
                            ->visible(fn () => auth()->user()->can('view_profit'))
                            ->columnSpan(1),

                        Forms\Components\Placeholder::make('profit_indicator')
                            ->label('مستوى الربحية')
                            ->content(function (Get $get) {
                                if (! auth()->user()->can('view_profit')) {
                                    return '—';
                                }

                                $totalRevenue = 0;
                                $totalCost = 0;
                                $items = $get('items') ?? [];

                                // Optimize: Batch load products to avoid N+1
                                $productIds = collect($items)->pluck('product_id')->filter()->unique()->toArray();
                                if (empty($productIds)) {
                                    return new \Illuminate\Support\HtmlString('<span style="color: gray">No Data</span>');
                                }
                                
                                $products = \App\Models\Product::whereIn('id', $productIds)->get()->keyBy('id');

                                foreach ($items as $item) {
                                    if (! isset($item['product_id'], $item['quantity'])) {
                                        continue;
                                    }

                                    $product = $products->get($item['product_id']);
                                    if (! $product) {
                                        continue;
                                    }

                                    $quantity = intval($item['quantity']);
                                    $unitType = $item['unit_type'] ?? 'small';
                                    $itemTotal = floatval($item['total'] ?? 0);

                                    $baseQuantity = $unitType === 'large' && $product->factor
                                        ? $quantity * $product->factor
                                        : $quantity;

                                    $costPerUnit = floatval($product->avg_cost ?? 0);
                                    $totalCost += $costPerUnit * $baseQuantity;
                                    $totalRevenue += $itemTotal;
                                }

                                // Apply discount
                                $discountType = $get('discount_type') ?? 'fixed';
                                $discountValue = floatval($get('discount_value') ?? 0);
                                $discount = $discountType === 'percentage'
                                    ? $totalRevenue * ($discountValue / 100)
                                    : $discountValue;

                                $netRevenue = $totalRevenue - $discount;
                                $totalProfit = $netRevenue - $totalCost;
                                $marginPct = $netRevenue > 0 ? ($totalProfit / $netRevenue) * 100 : 0;

                                // Get thresholds from settings
                                $excellentThreshold = floatval(\App\Models\GeneralSetting::getValue('profit_margin_excellent', 25));
                                $goodThreshold = floatval(\App\Models\GeneralSetting::getValue('profit_margin_good', 15));
                                $warnBelowCost = \App\Models\GeneralSetting::getValue('profit_margin_warning_below_cost', true);

                                // Check if selling below cost
                                if ($warnBelowCost && $totalProfit < 0) {
                                    return new \Illuminate\Support\HtmlString(
                                        '<span style="color: #ef4444; font-weight: bold;">⚠️ تحذير: البيع بأقل من التكلفة!</span> '.
                                        '<br><span style="color: #ef4444;">(خسارة: '.number_format(abs($marginPct), 1).'%)</span>'
                                    );
                                }

                                return match (true) {
                                    $marginPct >= $excellentThreshold => '🟢 ممتاز ('.number_format($marginPct, 1).'%)',
                                    $marginPct >= $goodThreshold => '🟡 جيد ('.number_format($marginPct, 1).'%)',
                                    default => '🔴 منخفض ('.number_format($marginPct, 1).'%)',
                                };
                            })
                            ->visible(fn () => auth()->user()->can('view_profit'))
                            ->columnSpan(1),

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
                                if (! $state) {
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

                        // Installment Schedule Preview
                        Forms\Components\Placeholder::make('installment_preview')
                            ->label('معاينة جدول الأقساط')
                            ->content(function (Get $get) {
                                $hasInstallment = $get('has_installment_plan');
                                $months = intval($get('installment_months') ?? 3);
                                $startDate = $get('installment_start_date');
                                $remainingAmount = floatval($get('remaining_amount') ?? 0);

                                if (! $hasInstallment || ! $startDate || $remainingAmount <= 0) {
                                    return '—';
                                }

                                $installmentAmount = $remainingAmount / $months;
                                $html = '<div class="overflow-x-auto">';
                                $html .= '<table class="w-full text-sm border-collapse">';
                                $html .= '<thead><tr class="bg-gray-100 dark:bg-gray-800">';
                                $html .= '<th class="p-2 text-center border border-gray-300 dark:border-gray-600">رقم القسط</th>';
                                $html .= '<th class="p-2 text-center border border-gray-300 dark:border-gray-600">تاريخ الاستحقاق</th>';
                                $html .= '<th class="p-2 text-center border border-gray-300 dark:border-gray-600">المبلغ</th>';
                                $html .= '</tr></thead><tbody>';

                                $currentDate = \Carbon\Carbon::parse($startDate);
                                for ($i = 1; $i <= $months; $i++) {
                                    $html .= '<tr class="border-t">';
                                    $html .= "<td class='p-2 text-center border border-gray-300 dark:border-gray-600'>القسط {$i}</td>";
                                    $html .= "<td class='p-2 text-center border border-gray-300 dark:border-gray-600'>{$currentDate->format('Y-m-d')}</td>";
                                    $html .= "<td class='p-2 text-center border border-gray-300 dark:border-gray-600'>".number_format($installmentAmount, 2).' ج.م</td>';
                                    $html .= '</tr>';
                                    $currentDate->addMonth();
                                }

                                $html .= '</tbody><tfoot><tr class="bg-gray-100 dark:bg-gray-800 font-bold">';
                                $html .= '<td colspan="2" class="p-2 text-center border border-gray-300 dark:border-gray-600">الإجمالي</td>';
                                $html .= '<td class="p-2 text-center border border-gray-300 dark:border-gray-600">'.number_format($remainingAmount, 2).' ج.م</td>';
                                $html .= '</tr></tfoot></table>';
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->visible(fn (Get $get) => $get('has_installment_plan'))
                            ->columnSpanFull(),
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

        // NEW: Recalculate commission
        $commissionRate = floatval($get('commission_rate') ?? 0) / 100;
        $commissionAmount = $netTotal * $commissionRate;
        $set('commission_amount', $commissionAmount);

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
            ->modifyQueryUsing(fn ($query) => $query->with(['partner', 'warehouse', 'creator', 'items.product'])->withSum('payments', 'amount'))
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
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('returns_count')
                    ->label('المرتجعات')
                    ->counts('returns')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'مؤكدة' : 'مسودة')
                    ->color(fn (string $state): string => $state === 'posted' ? 'success' : 'warning'),
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
                    ->color(fn (string $state): string => $state === 'cash' ? 'success' : 'info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('profit_margin')
                    ->label('هامش الربح')
                    ->state(function ($record) {
                        if (! auth()->user()->can('view_profit')) {
                            return null;
                        }

                        $totalProfit = 0;
                        foreach ($record->items as $item) {
                            $product = $item->product;
                            if (! $product) {
                                continue;
                            }

                            $baseQty = $item->unit_type === 'large' && $product->factor
                                ? $item->quantity * $product->factor
                                : $item->quantity;

                            $cost = floatval($product->avg_cost ?? 0) * $baseQty;
                            $totalProfit += ($item->total - $cost);
                        }

                        $marginPct = $record->total > 0 ? ($totalProfit / $record->total) * 100 : 0;

                        return $marginPct;
                    })
                    ->formatStateUsing(fn ($state) => $state !== null ? number_format($state, 1).'%' : '—')
                    ->badge()
                    ->color(function ($state) {
                        if ($state === null) {
                            return 'gray';
                        }

                        $excellent = floatval(\App\Models\GeneralSetting::getValue('profit_margin_excellent', 25));
                        $good = floatval(\App\Models\GeneralSetting::getValue('profit_margin_good', 15));

                        return match (true) {
                            $state < 0 => 'danger',
                            $state >= $excellent => 'success',
                            $state >= $good => 'warning',
                            default => 'gray',
                        };
                    })
                    ->visible(fn () => auth()->user()->can('view_profit'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('salesperson.name')
                    ->label('المندوب')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('commission_amount')
                    ->label('العمولة')
                    ->numeric(decimalPlaces: 2)
                    ->badge()
                    ->color(fn ($record) => $record->commission_paid ? 'success' : 'warning')
                    ->formatStateUsing(function ($record) {
                        if (! $record->sales_person_id || $record->commission_amount <= 0) {
                            return '—';
                        }
                        $amount = number_format($record->commission_amount, 2);
                        $status = $record->commission_paid ? '✓' : '✗';

                        return "{$amount} {$status}";
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->persistFiltersInSession()
            ->filters([
                // Quick Filter Pills
                ...\App\Filament\Components\QuickFilterPills::make(),
                \App\Filament\Components\QuickFilterPills::unpaidFilter(),
                \App\Filament\Components\QuickFilterPills::draftFilter(),

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
                    ->modalHeading('معاينة تأكيد الفاتورة')
                    ->modalDescription('مراجعة التغييرات التي ستحدث عند التأكيد')
                    ->modalSubmitActionLabel('تأكيد الفاتورة')
                    ->modalWidth('2xl')
                    ->fillForm(function (SalesInvoice $record) {
                        $stockService = app(StockService::class);
                        $changes = [];

                        foreach ($record->items as $item) {
                            $currentStock = $stockService->getCurrentStock(
                                $record->warehouse_id,
                                $item->product_id
                            );
                            $baseQty = $stockService->convertToBaseUnit(
                                $item->product,
                                $item->quantity,
                                $item->unit_type
                            );

                            $changes[] = [
                                'product' => $item->product->name,
                                'current_stock' => $currentStock,
                                'new_stock' => $currentStock - $baseQty,
                                'change' => -$baseQty,
                            ];
                        }

                        return [
                            'stock_changes' => $changes,
                            'treasury_impact' => $record->paid_amount,
                            'partner_balance_change' => $record->remaining_amount,
                        ];
                    })
                    ->form([
                        Forms\Components\Section::make('حركات المخزون')
                            ->description('التغييرات التي ستحدث على المخزون')
                            ->schema([
                                Forms\Components\Repeater::make('stock_changes')
                                    ->label('')
                                    ->schema([
                                        Forms\Components\TextInput::make('product')
                                            ->label('المنتج')
                                            ->disabled()
                                            ->dehydrated(false),
                                        Forms\Components\TextInput::make('current_stock')
                                            ->label('المخزون الحالي')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->numeric(),
                                        Forms\Components\TextInput::make('change')
                                            ->label('التغيير')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->numeric()
                                            ->extraAttributes(fn (Get $get) => [
                                                'style' => ($get('change') ?? 0) < 0 ? 'color: #ef4444; font-weight: bold;' : '',
                                            ]),
                                        Forms\Components\TextInput::make('new_stock')
                                            ->label('المخزون الجديد')
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->numeric()
                                            ->extraAttributes(fn (Get $get) => [
                                                'style' => ($get('new_stock') ?? 0) < 0 ? 'color: #ef4444; font-weight: bold;' : '',
                                            ]),
                                    ])
                                    ->columns(4)
                                    ->disabled()
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false),
                            ])
                            ->collapsible(),

                        Forms\Components\Section::make('حركات الخزينة')
                            ->description('التأثير المالي')
                            ->schema([
                                Forms\Components\Placeholder::make('treasury_impact')
                                    ->label('الدخول إلى الخزينة')
                                    ->content(fn ($state) => number_format($state ?? 0, 2).' ج.م')
                                    ->extraAttributes(['style' => 'color: #10b981; font-size: 1.25rem; font-weight: bold;']),
                                Forms\Components\Placeholder::make('partner_balance_change')
                                    ->label('رصيد العميل (المبلغ المتبقي)')
                                    ->content(fn ($state) => number_format($state ?? 0, 2).' ج.م')
                                    ->visible(fn (Get $get) => ($get('partner_balance_change') ?? 0) > 0)
                                    ->extraAttributes(['style' => 'color: #f59e0b; font-size: 1.25rem; font-weight: bold;']),
                            ]),
                    ])
                    ->action(function (SalesInvoice $record) {
                        // Validate invoice has items
                        if ($record->items()->count() === 0) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن تأكيد الفاتورة')
                                ->body('الفاتورة لا تحتوي على أي أصناف')
                                ->send();

                            return;
                        }

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
                                    ->step(0.01)
                                    ->default(fn (SalesInvoice $record) => floatval($record->current_remaining))
                                    ->rules([
                                        'required',
                                        'numeric',
                                        'min:0.01',
                                        fn (SalesInvoice $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
                                            $remainingAmount = floatval($record->current_remaining);
                                            if (floatval($value) > $remainingAmount) {
                                                $fail('لا يمكن دفع مبلغ ('.number_format($value, 2).') أكبر من المبلغ المتبقي ('.number_format($remainingAmount, 2).').');
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
                    ->visible(fn (SalesInvoice $record) => $record->isPosted() &&
                        ! $record->isFullyPaid()
                    ),

                Tables\Actions\Action::make('pay_commission')
                    ->label('دفع العمولة')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('دفع عمولة مندوب المبيعات')
                    ->form([
                        Forms\Components\Placeholder::make('salesperson_name')
                            ->label('المندوب')
                            ->content(fn (SalesInvoice $record) => $record->salesperson?->name ?? '—'),

                        Forms\Components\Placeholder::make('commission_amount_display')
                            ->label('قيمة العمولة')
                            ->content(fn (SalesInvoice $record) => number_format($record->commission_amount, 2).' ج.م'),

                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->options(Treasury::pluck('name', 'id'))
                            ->required()
                            ->default(fn () => Treasury::first()?->id),
                    ])
                    ->action(function (SalesInvoice $record, array $data) {
                        $commissionService = app(\App\Services\CommissionService::class);

                        try {
                            $commissionService->payCommission($record, $data['treasury_id']);

                            Notification::make()
                                ->success()
                                ->title('تم دفع العمولة بنجاح')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في دفع العمولة')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (SalesInvoice $record) => $record->isPosted() &&
                        $record->sales_person_id &&
                        ! $record->commission_paid &&
                        $record->commission_amount > 0
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(fn (SalesInvoice $record) => $record->isDraft()),
                Tables\Actions\ReplicateAction::make()
                    ->excludeAttributes(['invoice_number', 'status', 'payments_sum_amount', 'returns_count'])
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->invoice_number = 'SI-'.now()->format('Ymd').'-'.\Illuminate\Support\Str::random(6);
                        $replica->status = 'draft';
                        $replica->discount_value = $replica->discount_value ?? 0;
                        $replica->discount = $replica->discount ?? 0;
                    })
                    ->after(function (SalesInvoice $record, SalesInvoice $replica) {
                        // Copy invoice items manually since relationships aren't auto-replicated
                        foreach ($record->items as $item) {
                            $replica->items()->create([
                                'product_id' => $item->product_id,
                                'unit_type' => $item->unit_type,
                                'quantity' => $item->quantity,
                                'unit_price' => $item->unit_price,
                                'total' => $item->total,
                            ]);
                        }
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, SalesInvoice $record) {
                        if ($record->hasAssociatedRecords()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن الحذف')
                                ->body('لا يمكن حذف الفاتورة لوجود حركات مخزون أو خزينة أو مدفوعات مرتبطة بها أو لأنها مؤكدة.')
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_post')
                        ->label('تأكيد المحدد')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('تأكيد الفواتير المحددة')
                        ->modalDescription('هل تريد تأكيد جميع الفواتير المحددة؟ سيتم تسجيل حركات المخزون والخزينة.')
                        ->action(function (Collection $records) {
                            $stockService = app(StockService::class);
                            $treasuryService = app(TreasuryService::class);
                            $successCount = 0;
                            $errors = [];

                            // Eager load relationships to avoid lazy loading issues
                            $records->load('items.product');

                            foreach ($records as $record) {
                                if (! $record->isDraft()) {
                                    continue;
                                }

                                // Validate invoice has items
                                if ($record->items()->count() === 0) {
                                    $errors[] = "فاتورة {$record->invoice_number}: الفاتورة لا تحتوي على أي أصناف";

                                    continue;
                                }

                                try {
                                    DB::transaction(function () use ($record, $stockService, $treasuryService) {
                                        $stockService->postSalesInvoice($record);
                                        $treasuryService->postSalesInvoice($record);
                                        $record->update(['status' => 'posted']);
                                    });
                                    $successCount++;
                                } catch (\Exception $e) {
                                    $errors[] = "فاتورة {$record->invoice_number}: {$e->getMessage()}";
                                }
                            }

                            if ($successCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title("تم تأكيد {$successCount} فاتورة بنجاح")
                                    ->send();
                            }

                            if (! empty($errors)) {
                                Notification::make()
                                    ->danger()
                                    ->title('بعض الفواتير فشلت')
                                    ->body(implode("\n", array_slice($errors, 0, 5)))
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $skippedCount = 0;
                            $deletedCount = 0;

                            $records->each(function (SalesInvoice $record) use (&$skippedCount, &$deletedCount) {
                                if ($record->hasAssociatedRecords()) {
                                    $skippedCount++;
                                } else {
                                    $record->delete();
                                    $deletedCount++;
                                }
                            });

                            if ($deletedCount > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('تم الحذف بنجاح')
                                    ->body("تم حذف {$deletedCount} فاتورة")
                                    ->send();
                            }

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('تم تخطي بعض الفواتير')
                                    ->body("لم يتم حذف {$skippedCount} فاتورة لوجود حركات مالية مرتبطة أو لكونها مؤكدة")
                                    ->send();
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
