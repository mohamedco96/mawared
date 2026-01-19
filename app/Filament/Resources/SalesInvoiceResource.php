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
                // Header Section: Invoice Info & Partner Details
                Forms\Components\Group::make()
                    ->schema([
                        // Left Column: Invoice Details
                        Forms\Components\Section::make('معلومات الفاتورة')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('invoice_number')
                                            ->label('رقم الفاتورة')
                                            ->default(fn () => 'SI-'.now()->format('Ymd').'-'.Str::random(6))
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->readOnly()
                                            ->dehydrated(),

                                        Forms\Components\DatePicker::make('invoice_date') // Assuming created_at or adding a new field, typically invoice_date
                                            ->label('تاريخ الفاتورة')
                                            ->default(now())
                                            ->required(),

                                        Forms\Components\Select::make('warehouse_id')
                                            ->label('المخزن')
                                            ->relationship('warehouse', 'name', fn ($query) => $query->where('is_active', true))
                                            ->required()
                                            ->searchable()
                                            ->preload()
                                            ->default(fn () => Warehouse::where('is_active', true)->first()?->id ?? Warehouse::first()?->id)
                                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),

                                        Forms\Components\Select::make('sales_person_id')
                                            ->label('مندوب المبيعات')
                                            ->relationship('salesperson', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->default(auth()->id())
                                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),

                                        Forms\Components\TextInput::make('commission_rate')
                                            ->label('نسبة العمولة (%)')
                                            ->numeric()
                                            ->suffix('%')
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->default(1)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                static::recalculateTotals($set, $get);
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
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                static::recalculateTotals($set, $get);
                                            })
                                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),

                                        Forms\Components\Select::make('status')
                                            ->label('الحالة')
                                            ->options([
                                                'draft' => 'مسودة',
                                                'posted' => 'مؤكدة',
                                            ])
                                            ->default('draft')
                                            ->required()
                                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                    ]),
                            ])->columnSpan(2),

                        // Right Column: Partner Details (Customer)
                        Forms\Components\Section::make('بيانات العميل')
                            ->schema([
                                Forms\Components\Select::make('partner_id')
                                    ->label('العميل')
                                    ->relationship('partner', 'name', fn ($query) => $query->where('type', 'customer'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\TextInput::make('phone'),
                                        Forms\Components\TextInput::make('address'),
                                        Forms\Components\Hidden::make('type')->default('customer'),
                                    ])
                                    ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),

                                // Dynamic Partner Card Component
                                Forms\Components\Placeholder::make('partner_card')
                                    ->label('')
                                    ->content(function (Get $get) {
                                        $partnerId = $get('partner_id');

                                        return $partnerId
                                            ? view('filament.components.partner-card', [
                                                'partner' => \App\Models\Partner::find($partnerId),
                                            ])
                                            : null;
                                    })
                                    ->hidden(fn (Get $get) => ! $get('partner_id')),
                            ])->columnSpan(1),
                    ])->columns(3)->columnSpanFull(),

                // Items Section
                Forms\Components\Section::make('أصناف الفاتورة')
                    ->headerActions([
                        // Optional: Actions could go here
                    ])
                    ->schema([
                        // 1. Product Search / Scanner Bar
                        Forms\Components\Select::make('product_scanner')
                            ->label('بحث سريع / باركود (إضافة صنف)')
                            ->searchable()
                            ->preload()
                            ->options(function (Get $get) {
                                $warehouseId = $get('warehouse_id');

                                return Product::latest()->limit(20)->get()->mapWithKeys(function ($product) use ($warehouseId) {
                                    $stock = 0;
                                    if ($warehouseId) {
                                        $stock = app(\App\Services\StockService::class)->getCurrentStock($warehouseId, $product->id);
                                    }

                                    return [$product->id => "{$product->name} (المتوفر: {$stock}) - {$product->retail_price} ج.م"];
                                })->toArray();
                            })
                            ->placeholder('ابحث عن منتج بالاسم أو الباركود...')
                            ->getSearchResultsUsing(function (?string $search, Get $get): array {
                                $warehouseId = $get('warehouse_id');
                                $query = Product::query();
                                if (! empty($search)) {
                                    $query->where(function ($q) use ($search) {
                                        $q->where('name', 'like', "%{$search}%")
                                            ->orWhere('sku', 'like', "%{$search}%")
                                            ->orWhere('barcode', 'like', "%{$search}%");
                                    });
                                } else {
                                    $query->latest()->limit(10);
                                }

                                return $query->limit(20)->get()->mapWithKeys(function ($product) use ($warehouseId) {
                                    // Stock info
                                    $stock = 0;
                                    if ($warehouseId) {
                                        $stock = app(\App\Services\StockService::class)->getCurrentStock($warehouseId, $product->id);
                                    }

                                    return [$product->id => "{$product->name} (المتوفر: {$stock}) - {$product->retail_price} ج.م"];
                                })->toArray();
                            })
                            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }
                                $product = Product::find($state);
                                if (! $product) {
                                    return;
                                }

                                // Add item to repeater
                                $items = $get('items') ?? [];
                                $uuid = (string) Str::uuid();

                                // Determine price (using retail/wholesale logic if needed, defaulting to retail here or unit price)
                                $unitType = 'small';
                                $price = $product->wholesale_price > 0 ? $product->wholesale_price : $product->retail_price;

                                $items[$uuid] = [
                                    'product_id' => $product->id,
                                    'unit_type' => $unitType,
                                    'quantity' => 1,
                                    'unit_price' => $price,
                                    'total' => $price * 1,
                                    'discount' => 0,
                                ];

                                $set('items', $items);
                                $set('product_scanner', null); // Reset scanner

                                // Recalculate
                                static::recalculateTotals($set, $get);

                                Notification::make()->title('تم إضافة الصنف')->success()->send();
                            })
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),

                        // 2. Items Repeater (Simulating Table)
                        Forms\Components\Repeater::make('items')
                            ->label('قائمة الأصناف')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        // Product Name (Read Only)
                                        Forms\Components\Select::make('product_id')
                                            ->label('المنتج')
                                            ->options(Product::pluck('name', 'id'))
                                            ->disabled()
                                            ->dehydrated() // Save the ID
                                            ->columnSpan(4)
                                            ->required(),

                                        // Unit Type
                                        Forms\Components\Select::make('unit_type')
                                            ->label('الوحدة')
                                            ->options([
                                                'small' => 'صغيرة',
                                                'large' => 'كبيرة',
                                            ])
                                            ->default('small')
                                            ->required()
                                            ->live()
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                // Update price based on unit
                                                $productId = $get('product_id');
                                                if ($productId && $product = Product::find($productId)) {
                                                    $price = ($state === 'large' && $product->large_wholesale_price)
                                                       ? $product->large_wholesale_price
                                                       : $product->wholesale_price;
                                                    $set('unit_price', $price);
                                                    $set('total', $price * ($get('quantity') ?? 1));
                                                    static::recalculateTotals($set, $get);
                                                }
                                            })
                                            ->columnSpan(2),

                                        // Quantity
                                        Forms\Components\TextInput::make('quantity')
                                            ->label('الكمية')
                                            ->integer()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                                            ->default(1)
                                            ->minValue(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $unitPrice = $get('unit_price') ?? 0;
                                                $set('total', $unitPrice * $state);
                                                static::recalculateTotals($set, $get);
                                            })
                                            ->helperText(function (Get $get) {
                                                $productId = $get('product_id');
                                                $warehouseId = $get('../../warehouse_id');
                                                $unitType = $get('unit_type') ?? 'small';

                                                if (! $productId || ! $warehouseId) {
                                                    return null;
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
                                            ->columnSpan(2),

                                        // Unit Price
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
                                                static::recalculateTotals($set, $get);
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
                                            ->columnSpan(2),

                                        // Total
                                        Forms\Components\TextInput::make('total')
                                            ->label('المجموع')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->columnSpanFull()
                            ->addable(false)
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->collapseAllAction(fn ($action) => $action->label('طي الكل'))
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord)
                            ->live()
                            ->afterStateUpdated(fn (Set $set, Get $get) => static::recalculateTotals($set, $get)),
                    ]),

                // Summary & Totals Section
                Forms\Components\Section::make('ملخص الفاتورة والمدفوعات')
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                // --- RIGHT SIDE: SUMMARY (Span 4) ---
                                Forms\Components\Group::make()
                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 12)
                                    ->schema([
                                        Forms\Components\Section::make()
                                            ->columns(4)
                                            ->schema([
                                                // Total Items
                                                Forms\Components\Placeholder::make('total_items_count')
                                                    ->label('عدد الأصناف')
                                                    ->content(function (Get $get) {
                                                        $items = $get('items') ?? [];

                                                        return count($items).' صنف';
                                                    })
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 1),

                                                // Subtotal
                                                Forms\Components\TextInput::make('subtotal')
                                                    ->label('المجموع الفرعي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->prefix('ج.م')
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 1),

                                                // Discount
                                                Forms\Components\Grid::make(2)
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 2)
                                                    ->schema([
                                                        Forms\Components\Select::make('discount_type')
                                                            ->label('نوع الخصم')
                                                            ->options([
                                                                'fixed' => 'مبلغ',
                                                                'percentage' => 'نسبة %',
                                                            ])
                                                            ->default('fixed')
                                                            ->live()
                                                            ->afterStateUpdated(fn (Set $set, Get $get) => static::recalculateTotals($set, $get)),

                                                        Forms\Components\TextInput::make('discount_value')
                                                            ->label('قيمة الخصم')
                                                            ->numeric()
                                                            ->default(0)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(fn (Set $set, Get $get) => static::recalculateTotals($set, $get)),
                                                    ]),

                                                // Tax (Hidden/Placeholder)
                                                Forms\Components\TextInput::make('tax_amount')
                                                    ->label('ضريبة القيمة المضافة')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->readOnly()
                                                    ->visible(false)
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 1),

                                                // Total (Highlighted)
                                                Forms\Components\TextInput::make('total')
                                                    ->label('الإجمالي النهائي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->prefix('ج.م')
                                                    ->extraInputAttributes(['style' => 'font-size: 1.5rem; font-weight: bold; color: #16a34a; text-align: center'])
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 2),

                                                // Credit Payment Fields
                                                Forms\Components\TextInput::make('paid_amount')
                                                    ->label('المدفوع مقدماً')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->visible(fn (Get $get) => $get('payment_method') === 'credit')
                                                    ->afterStateUpdated(fn (Set $set, Get $get) => static::recalculateTotals($set, $get))
                                                    ->columnSpan(4),

                                                Forms\Components\TextInput::make('remaining_amount')
                                                    ->label('المتبقي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->visible(fn (Get $get) => $get('payment_method') === 'credit')
                                                    ->extraInputAttributes(['style' => 'color: #dc2626; font-weight: bold;'])
                                                    ->columnSpan(4),

                                                // Commission & Profit
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
                                                    ->extraAttributes(['style' => 'color: #f59e0b; font-weight: bold;'])
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 1),

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
                                                    ->visible(fn () => auth()->user()->can('view_profit')),

                                                Forms\Components\Hidden::make('discount')->default(0),
                                                Forms\Components\Hidden::make('commission_amount')->default(0),
                                            ]),
                                    ]),

                                // --- LEFT SIDE: INSTALLMENTS (Span 8) ---
                                Forms\Components\Group::make()
                                    ->columnSpan(8)
                                    ->visible(fn (Get $get) => $get('payment_method') === 'credit')
                                    ->schema([
                                        Forms\Components\Section::make('إعدادات التقسيط')
                                            ->extraAttributes(['class' => 'bg-gradient-to-br from-blue-50 via-white to-white dark:from-blue-900/20 dark:via-gray-800 dark:to-gray-800 border-blue-100 dark:border-blue-800 shadow-sm'])
                                            ->schema([
                                                Forms\Components\Toggle::make('has_installment_plan')
                                                    ->label('تفعيل خطة التقسيط للمبلغ المتبقي')
                                                    ->default(false)
                                                    ->live()
                                                    ->afterStateUpdated(function ($state, Set $set) {
                                                        if (! $state) {
                                                            $set('installment_months', null);
                                                            $set('installment_start_date', null);
                                                            $set('installment_notes', null);
                                                        }
                                                    }),

                                                Forms\Components\Grid::make(3)
                                                    ->visible(fn (Get $get) => $get('has_installment_plan'))
                                                    ->schema([
                                                        Forms\Components\TextInput::make('installment_months')
                                                            ->label('عدد الأقساط')
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->maxValue(120)
                                                            ->default(3)
                                                            ->required(),

                                                        Forms\Components\DatePicker::make('installment_start_date')
                                                            ->label('تاريخ أول قسط')
                                                            ->required()
                                                            ->default(now()->addMonth()->startOfMonth()),

                                                        Forms\Components\Textarea::make('installment_notes')
                                                            ->label('ملاحظات التقسيط')
                                                            ->rows(1),
                                                    ]),

                                                Forms\Components\Placeholder::make('installment_preview')
                                                    ->label('جدول الأقساط المقترح')
                                                    ->visible(fn (Get $get) => $get('has_installment_plan'))
                                                    ->content(function (Get $get) {
                                                        $hasInstallment = $get('has_installment_plan');
                                                        $months = intval($get('installment_months') ?? 3);
                                                        $startDate = $get('installment_start_date');
                                                        $remainingAmount = floatval($get('remaining_amount') ?? 0);

                                                        if (! $hasInstallment || ! $startDate || $remainingAmount <= 0) {
                                                            return '—';
                                                        }

                                                        $installmentAmount = $remainingAmount / $months;
                                                        $html = '<div class="overflow-x-auto mt-4">';
                                                        $html .= '<table class="w-full text-sm border-collapse border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">';
                                                        $html .= '<thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-200">';
                                                        $html .= '<tr>';
                                                        $html .= '<th class="p-3 text-center border-b dark:border-gray-700">رقم القسط</th>';
                                                        $html .= '<th class="p-3 text-center border-b dark:border-gray-700">تاريخ الاستحقاق</th>';
                                                        $html .= '<th class="p-3 text-center border-b dark:border-gray-700">المبلغ المستحق</th>';
                                                        $html .= '</tr></thead><tbody class="divide-y divide-gray-200 dark:divide-gray-700">';

                                                        $currentDate = \Carbon\Carbon::parse($startDate);
                                                        for ($i = 1; $i <= $months; $i++) {
                                                            $bgClass = $i % 2 === 0 ? 'bg-gray-50 dark:bg-gray-800/50' : '';
                                                            $html .= "<tr class='{$bgClass}'>";
                                                            $html .= "<td class='p-3 text-center font-medium'>{$i}</td>";
                                                            $html .= "<td class='p-3 text-center'>{$currentDate->format('Y-m-d')}</td>";
                                                            $html .= "<td class='p-3 text-center font-bold text-primary-600'>".number_format($installmentAmount, 2).' ج.م</td>';
                                                            $html .= '</tr>';
                                                            $currentDate->addMonth();
                                                        }

                                                        $html .= '</tbody><tfoot><tr class="bg-gray-100 dark:bg-gray-800 font-bold text-lg">';
                                                        $html .= '<td colspan="2" class="p-3 text-center">الإجمالي</td>';
                                                        $html .= '<td class="p-3 text-center text-primary-700">'.number_format($remainingAmount, 2).' ج.م</td>';
                                                        $html .= '</tr></tfoot></table>';
                                                        $html .= '</div>';

                                                        return new \Illuminate\Support\HtmlString($html);
                                                    }),
                                            ]),
                                    ]),

                                // --- BOTTOM: NOTES (Span 12) ---
                                Forms\Components\Section::make('ملاحظات إضافية')
                                    ->columnSpan(12)
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->hiddenLabel()
                                            ->placeholder('أدخل أي ملاحظات إضافية هنا...')
                                            ->rows(3),
                                    ]),
                            ]),
                    ]),
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
