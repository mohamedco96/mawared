<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Resources\PurchaseInvoiceResource\RelationManagers;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseInvoice;
use App\Models\Treasury;
use App\Models\Unit;
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
                                            ->default(fn () => 'PI-'.now()->format('Ymd').'-'.Str::random(6))
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->readOnly()
                                            ->dehydrated(),

                                        Forms\Components\DatePicker::make('invoice_date')
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

                        // Right Column: Partner Details (Supplier)
                        Forms\Components\Section::make('بيانات المورد')
                            ->schema([
                                Forms\Components\Select::make('partner_id')
                                    ->label('المورد')
                                    ->relationship('partner', 'name', fn ($query) => $query->where('type', 'supplier'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')->required(),
                                        Forms\Components\TextInput::make('phone'),
                                        Forms\Components\TextInput::make('address'),
                                        Forms\Components\Hidden::make('type')->default('supplier'),
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
                        Forms\Components\Actions\Action::make('create_product')
                            ->label('تعريف منتج جديد')
                            ->icon('heroicon-o-plus')
                            ->color('success')
                            ->form([
                                Forms\Components\TextInput::make('name')
                                    ->label('اسم المنتج')
                                    ->required(),
                                Forms\Components\Select::make('category_id')
                                    ->label('التصنيف')
                                    ->options(ProductCategory::pluck('name', 'id'))
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->label('اسم التصنيف'),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return ProductCategory::create($data)->id;
                                    }),
                                Forms\Components\Select::make('small_unit_id')
                                    ->label('الوحدة الأساسية')
                                    ->options(Unit::pluck('name', 'id'))
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->label('اسم الوحدة'),
                                    ])
                                    ->createOptionUsing(function (array $data) {
                                        return Unit::create($data)->id;
                                    }),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('avg_cost')
                                            ->label('سعر التكلفة')
                                            ->numeric()
                                            ->required(),
                                        Forms\Components\TextInput::make('retail_price')
                                            ->label('سعر البيع')
                                            ->numeric()
                                            ->required(),
                                    ]),
                                Forms\Components\TextInput::make('barcode')
                                    ->label('الباركود')
                                    ->placeholder('اتركه فارغاً للتوليد التلقائي'),
                            ])
                            ->action(function (array $data, Set $set, Get $get) {
                                $product = Product::create([
                                    'name' => $data['name'],
                                    'category_id' => $data['category_id'],
                                    'small_unit_id' => $data['small_unit_id'],
                                    'avg_cost' => $data['avg_cost'],
                                    'retail_price' => $data['retail_price'],
                                    'barcode' => $data['barcode'] ?? null,
                                    'sku' => Str::random(8), // Or generate properly
                                    'is_active' => true,
                                ]);

                                Notification::make()
                                    ->title('تم إضافة المنتج بنجاح')
                                    ->success()
                                    ->send();

                                // Add to items repeater
                                $items = $get('items') ?? [];
                                $uuid = (string) Str::uuid();

                                $items[$uuid] = [
                                    'product_id' => $product->id,
                                    'unit_type' => 'small',
                                    'quantity' => 1,
                                    'unit_cost' => $product->avg_cost ?: 0,
                                    'total' => ($product->avg_cost ?: 0) * 1,
                                    'discount' => 0,
                                ];

                                $set('items', $items);
                                static::recalculateTotals($set, $get);
                            }),
                    ])
                    ->schema([
                        // 1. Product Search / Scanner Bar
                        Forms\Components\Select::make('product_scanner')
                            ->label('بحث سريع / باركود (إضافة صنف)')
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return Product::latest()->limit(20)->get()->mapWithKeys(function ($product) {
                                    return [$product->id => "{$product->name} - (تكلفة: {$product->avg_cost})"];
                                })->toArray();
                            })
                            ->placeholder('ابحث عن منتج بالاسم أو الباركود...')
                            ->getSearchResultsUsing(function (?string $search): array {
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

                                return $query->limit(20)->get()->mapWithKeys(function ($product) {
                                    return [$product->id => "{$product->name} - (تكلفة: {$product->avg_cost})"];
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

                                $items[$uuid] = [
                                    'product_id' => $product->id,
                                    'unit_type' => 'small',
                                    'quantity' => 1,
                                    'unit_cost' => $product->avg_cost ?: 0,
                                    'total' => ($product->avg_cost ?: 0) * 1,
                                    'discount' => 0,
                                ];

                                $set('items', $items);
                                $set('product_scanner', null);
                                static::recalculateTotals($set, $get);
                                Notification::make()->title('تم إضافة الصنف')->success()->send();
                            })
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),

                        // 2. Items Repeater
                        Forms\Components\Repeater::make('items')
                            ->label('قائمة الأصناف')
                            ->relationship('items')
                            ->addable(false)
                            ->addActionLabel('إضافة صنف يدوياً')
                            ->addAction(fn ($action) => $action->color('success'))
                            ->schema([
                                Forms\Components\Grid::make(12)
                                    ->schema([
                                        // Row 1: Basic Item Info
                                        Forms\Components\Select::make('product_id')
                                            ->label('المنتج')
                                            ->relationship('product', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->live(onBlur: true)
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('اسم المنتج')
                                                    ->required(),
                                                Forms\Components\Select::make('category_id')
                                                    ->label('القسم')
                                                    ->options(ProductCategory::pluck('name', 'id'))
                                                    ->required()
                                                    ->createOptionForm([
                                                        Forms\Components\TextInput::make('name')
                                                            ->required()
                                                            ->label('اسم القسم'),
                                                    ]),
                                                Forms\Components\Select::make('small_unit_id')
                                                    ->label('الوحدة الأساسية')
                                                    ->options(Unit::pluck('name', 'id'))
                                                    ->required()
                                                    ->createOptionForm([
                                                        Forms\Components\TextInput::make('name')
                                                            ->required()
                                                            ->label('اسم الوحدة'),
                                                    ]),
                                                Forms\Components\Grid::make(2)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('avg_cost')
                                                            ->label('سعر التكلفة')
                                                            ->numeric()
                                                            ->default(0)
                                                            ->required(),
                                                        Forms\Components\TextInput::make('retail_price')
                                                            ->label('سعر البيع')
                                                            ->numeric()
                                                            ->default(0)
                                                            ->required(),
                                                    ]),
                                                Forms\Components\TextInput::make('barcode')
                                                    ->label('الباركود')
                                                    ->placeholder('اتركه فارغاً للتوليد التلقائي'),
                                            ])
                                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                                return $action->modalHeading('إضافة منتج جديد')
                                                    ->modalWidth('lg');
                                            })
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                if ($state) {
                                                    $product = Product::find($state);
                                                    if ($product) {
                                                        $set('unit_cost', $product->avg_cost ?: 0);
                                                        // Trigger total recalculation
                                                        $quantity = $get('quantity') ?? 1;
                                                        $set('total', ($product->avg_cost ?: 0) * $quantity);
                                                        static::recalculateTotals($set, $get);
                                                    }
                                                }
                                            })
                                            ->columnSpan(4)
                                            ->required(),

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
                                            ->live()
                                            ->afterStateUpdated(fn (Set $set, Get $get) => static::recalculateTotals($set, $get))
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('quantity')
                                            ->label('الكمية')
                                            ->integer()
                                            ->default(1)
                                            ->minValue(1)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $unitCost = floatval($get('unit_cost') ?? 0);
                                                $quantity = intval($state);
                                                $set('total', $unitCost * $quantity);
                                                static::recalculateTotals($set, $get);
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('unit_cost')
                                            ->label('التكلفة')
                                            ->numeric()
                                            ->required()
                                            ->step(0.0001)
                                            ->minValue(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                $quantity = intval($get('quantity') ?? 1);
                                                $unitCost = floatval($state);
                                                $set('total', $unitCost * $quantity);
                                                static::recalculateTotals($set, $get);
                                            })
                                            ->columnSpan(2),

                                        Forms\Components\TextInput::make('total')
                                            ->label('الإجمالي')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated()
                                            ->columnSpan(2),

                                        // Row 2: Price Updates (Collapsible or just below)
                                        Forms\Components\Group::make()
                                            ->schema([
                                                Forms\Components\Grid::make(4)
                                                    ->schema([
                                                        Forms\Components\TextInput::make('new_selling_price')
                                                            ->label('بيع (صغير)')
                                                            ->placeholder('تحديث السعر')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                                $productId = $get('product_id');
                                                                if ($productId && $state) {
                                                                    $product = Product::find($productId);
                                                                    if ($product && $product->large_unit_id && $product->factor > 0) {
                                                                        $set('new_large_selling_price', number_format($state * $product->factor, 2, '.', ''));
                                                                    }
                                                                }
                                                            }),

                                                        Forms\Components\TextInput::make('new_large_selling_price')
                                                            ->label('بيع (كبير)')
                                                            ->placeholder('تحديث السعر')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->visible(fn (Get $get) => Product::find($get('product_id'))?->large_unit_id),

                                                        Forms\Components\TextInput::make('wholesale_price')
                                                            ->label('جملة (صغير)')
                                                            ->placeholder('تحديث السعر')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                                $productId = $get('product_id');
                                                                if ($productId && $state) {
                                                                    $product = Product::find($productId);
                                                                    if ($product && $product->large_unit_id && $product->factor > 0) {
                                                                        $set('large_wholesale_price', number_format($state * $product->factor, 2, '.', ''));
                                                                    }
                                                                }
                                                            }),

                                                        Forms\Components\TextInput::make('large_wholesale_price')
                                                            ->label('جملة (كبير)')
                                                            ->placeholder('تحديث السعر')
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->visible(fn (Get $get) => Product::find($get('product_id'))?->large_unit_id),
                                                    ]),
                                            ])
                                            ->columnSpan(12)
                                            ->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-800/50 p-2 rounded-lg mt-2']),
                                    ]),
                            ])
                            ->defaultItems(0)
                            ->columnSpanFull()
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
                                // --- RIGHT SIDE: SUMMARY ---
                                Forms\Components\Group::make()
                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 12)
                                    ->schema([
                                        Forms\Components\Section::make()
                                            ->columns(4)
                                            ->schema([
                                                Forms\Components\Placeholder::make('total_items_count')
                                                    ->label('عدد الأصناف')
                                                    ->content(function (Get $get) {
                                                        $items = $get('items') ?? [];

                                                        return count($items).' صنف';
                                                    })
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 1),

                                                Forms\Components\TextInput::make('subtotal')
                                                    ->label('المجموع الفرعي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->prefix('ج.م')
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 1),

                                                Forms\Components\Grid::make(2)
                                                    ->columnSpan(fn (Get $get) => $get('payment_method') === 'credit' ? 4 : 2)
                                                    ->schema([
                                                        Forms\Components\Select::make('discount_type')
                                                            ->label('نوع الخصم')
                                                            ->options(['fixed' => 'مبلغ', 'percentage' => 'نسبة %'])
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

                                                Forms\Components\TextInput::make('total')
                                                    ->label('الإجمالي النهائي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->prefix('ج.م')
                                                    ->extraInputAttributes(['style' => 'font-size: 1.5rem; font-weight: bold; color: #16a34a; text-align: center'])
                                                    ->columnSpan(4), // Full width in the card

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

                                                Forms\Components\Hidden::make('discount')->default(0),
                                            ]),
                                    ]),

                                // --- LEFT SIDE: NOTES/EXTRA (Span 8 if Credit) ---
                                Forms\Components\Group::make()
                                    ->columnSpan(8)
                                    ->visible(fn (Get $get) => $get('payment_method') === 'credit')
                                    ->schema([
                                        Forms\Components\Section::make('ملاحظات الدفع')
                                            ->schema([
                                                Forms\Components\Placeholder::make('credit_note_info')
                                                    ->content('يمكنك تسجيل الدفعات الجزئية هنا. المبلغ المتبقي سيتم تسجيله كدين على المورد.'),
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
        // Try to get items from current scope, or parent scope if inside repeater
        $items = $get('items');
        $pathPrefix = '';

        if ($items === null) {
            $items = $get('../../items');
            if ($items !== null) {
                $pathPrefix = '../../';
            } else {
                $items = [];
            }
        }

        $subtotal = collect($items)->sum('total');
        $discountType = $get($pathPrefix.'discount_type') ?? 'fixed';
        $discountValue = floatval($get($pathPrefix.'discount_value') ?? 0);
        $paymentMethod = $get($pathPrefix.'payment_method') ?? 'cash';

        // Calculate discount
        $totalDiscount = $discountType === 'percentage'
            ? $subtotal * ($discountValue / 100)
            : $discountValue;

        $netTotal = $subtotal - $totalDiscount;

        // Update hidden fields
        $set($pathPrefix.'subtotal', $subtotal);
        $set($pathPrefix.'discount', $totalDiscount); // OLD field for backward compatibility
        $set($pathPrefix.'total', $netTotal);

        // Auto-fill paid_amount based on payment method
        $currentPaidAmount = floatval($get($pathPrefix.'paid_amount') ?? 0);

        // Only auto-fill if payment method is cash or if current paid amount exceeds net total
        if ($paymentMethod === 'cash') {
            $set($pathPrefix.'paid_amount', $netTotal);
            $set($pathPrefix.'remaining_amount', 0);
        } else {
            // For credit, only reset if current paid_amount exceeds net total
            if ($currentPaidAmount > $netTotal) {
                $set($pathPrefix.'paid_amount', 0);
                $set($pathPrefix.'remaining_amount', $netTotal);
            } else {
                $set($pathPrefix.'remaining_amount', max(0, $netTotal - $currentPaidAmount));
            }
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['partner', 'warehouse', 'creator', 'items.product'])
                ->withSum('payments', 'amount')
            )
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
                    ->color(fn (string $state): string => $state === 'cash' ? 'success' : 'info')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->toggleable()
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

                                    ->step(0.01)
                                    ->default(fn (PurchaseInvoice $record) => floatval($record->current_remaining))
                                    ->rules([
                                        'required',
                                        'numeric',
                                        'min:0.01',
                                        fn (PurchaseInvoice $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
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
                    ->visible(fn (PurchaseInvoice $record) => $record->isPosted() &&
                        ! $record->isFullyPaid()
                    ),

                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseInvoice $record) => $record->isDraft()),
                Tables\Actions\ReplicateAction::make()
                    ->excludeAttributes(['invoice_number', 'status', 'payments_sum_amount', 'returns_count'])
                    ->beforeReplicaSaved(function ($replica) {
                        $replica->invoice_number = 'PI-'.now()->format('Ymd').'-'.\Illuminate\Support\Str::random(6);
                        $replica->status = 'draft';
                        $replica->discount_value = $replica->discount_value ?? 0;
                        $replica->discount = $replica->discount ?? 0;
                    })
                    ->after(function (PurchaseInvoice $record, PurchaseInvoice $replica) {
                        // Copy invoice items manually since relationships aren't auto-replicated
                        foreach ($record->items as $item) {
                            $replica->items()->create([
                                'product_id' => $item->product_id,
                                'unit_type' => $item->unit_type,
                                'quantity' => $item->quantity,
                                'unit_cost' => $item->unit_cost,
                                'total' => $item->total,
                                'new_selling_price' => $item->new_selling_price,
                                'new_large_selling_price' => $item->new_large_selling_price,
                                'wholesale_price' => $item->wholesale_price,
                                'large_wholesale_price' => $item->large_wholesale_price,
                            ]);
                        }
                    }),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, PurchaseInvoice $record) {
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
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $skippedCount = 0;
                            $deletedCount = 0;

                            $records->each(function (PurchaseInvoice $record) use (&$skippedCount, &$deletedCount) {
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
