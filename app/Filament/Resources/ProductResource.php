<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'المنتجات';

    protected static ?string $modelLabel = 'منتج';

    protected static ?string $pluralModelLabel = 'المنتجات';

    protected static ?string $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->whereHas('stockMovements', function ($query) {
                $query->select('product_id')
                    ->selectRaw('SUM(quantity) as total_stock')
                    ->groupBy('product_id')
                    ->havingRaw('SUM(quantity) <= products.min_stock AND SUM(quantity) > 0');
            })
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $hasLowStock = static::getModel()::query()
            ->whereHas('stockMovements', function ($query) {
                $query->select('product_id')
                    ->selectRaw('SUM(quantity) as total_stock')
                    ->groupBy('product_id')
                    ->havingRaw('SUM(quantity) <= products.min_stock AND SUM(quantity) > 0');
            })
            ->exists();

        return $hasLowStock ? 'danger' : 'gray';
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'SKU' => $record->sku,
            'الباركود' => $record->barcode,
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku', 'barcode'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Product Steps')
                    ->contained(true)
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('البيانات الأساسية')
                            ->icon('heroicon-m-information-circle')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('اسم المنتج')
                                            ->required()
                                            ->maxLength(255)
                                            ->autofocus(),
                                        Forms\Components\Select::make('category_id')
                                            ->label('التصنيف')
                                            ->relationship('category', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('اسم التصنيف')
                                                    ->required()
                                                    ->maxLength(255),
                                            ])
                                            ->nullable(),
                                    ]),
                                Forms\Components\Textarea::make('description')
                                    ->label('الوصف')
                                    ->columnSpanFull(),
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\Toggle::make('is_visible_in_retail_catalog')
                                            ->label('مرئي في كتالوج قطاعي')
                                            ->helperText('عند التفعيل، سيظهر المنتج في صالة العرض الرقمية للتجزئة')
                                            ->default(false)
                                            ->inline(false),
                                        Forms\Components\Toggle::make('is_visible_in_wholesale_catalog')
                                            ->label('مرئي في كتالوج الجملة')
                                            ->helperText('عند التفعيل، سيظهر المنتج في صالة العرض الرقمية للجملة')
                                            ->default(false)
                                            ->inline(false),
                                    ]),
                            ]),

                        Forms\Components\Tabs\Tab::make('الصور والميديا')
                            ->icon('heroicon-m-photo')
                            ->schema([
                                Forms\Components\FileUpload::make('image')
                                    ->label('الصورة الرئيسية')
                                    ->image()
                                    ->directory('products')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->maxSize(2048)
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                                    ->openable()
                                    ->downloadable()
                                    ->previewable()
                                    ->preserveFilenames()
                                    ->helperText('الصورة الرئيسية للمنتج (الحد الأقصى: 2 ميجابايت)')
                                    ->columnSpanFull(),
                                Forms\Components\FileUpload::make('images')
                                    ->label('صور إضافية')
                                    ->image()
                                    ->directory('products')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->multiple()
                                    ->maxFiles(10)
                                    ->maxSize(2048)
                                    ->imageEditor()
                                    ->imageEditorAspectRatios(['16:9', '4:3', '1:1'])
                                    ->openable()
                                    ->downloadable()
                                    ->previewable()
                                    ->preserveFilenames()
                                    ->helperText('يمكن إضافة حتى 10 صور إضافية (الحد الأقصى لكل صورة: 2 ميجابايت)')
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Tabs\Tab::make('التسعير والوحدات')
                            ->icon('heroicon-m-currency-dollar')
                            ->schema([
                                Forms\Components\Section::make('نظام الوحدات')
                                    ->schema([
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
                                            ->createOptionModalHeading('إضافة وحدة قياس جديدة')
                                            ->editOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('الاسم')
                                                    ->required(),
                                                Forms\Components\TextInput::make('symbol')
                                                    ->label('الرمز'),
                                            ]),
                                        Forms\Components\Select::make('large_unit_id')
                                            ->label('الوحدة الكبيرة (الكرتون)')
                                            ->relationship('largeUnit', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->nullable()
                                            ->createOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('الاسم')
                                                    ->required(),
                                                Forms\Components\TextInput::make('symbol')
                                                    ->label('الرمز'),
                                            ])
                                            ->createOptionModalHeading('إضافة وحدة قياس جديدة')
                                            ->editOptionForm([
                                                Forms\Components\TextInput::make('name')
                                                    ->label('الاسم')
                                                    ->required(),
                                                Forms\Components\TextInput::make('symbol')
                                                    ->label('الرمز'),
                                            ]),
                                        Forms\Components\TextInput::make('factor')
                                            ->label('معامل التحويل')
                                            ->helperText('عدد الوحدات الصغيرة في الوحدة الكبيرة')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->extraInputAttributes(['dir' => 'ltr'])
                                            ->default(1)
                                            ->required()
                                            ->minValue(1)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                $largeUnitId = $get('large_unit_id');
                                                $retailPrice = $get('retail_price');
                                                $wholesalePrice = $get('wholesale_price');

                                                // Recalculate large unit prices when factor changes
                                                if ($largeUnitId && $state !== null && $state > 0) {
                                                    if ($retailPrice !== null && $retailPrice !== '') {
                                                        $calculatedPrice = floatval($retailPrice) * intval($state);
                                                        $set('large_retail_price', number_format($calculatedPrice, 2, '.', ''));
                                                    }
                                                    if ($wholesalePrice !== null && $wholesalePrice !== '') {
                                                        $calculatedPrice = floatval($wholesalePrice) * intval($state);
                                                        $set('large_wholesale_price', number_format($calculatedPrice, 2, '.', ''));
                                                    }
                                                }
                                            }),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('الأسعار - الوحدة الصغيرة')
                                    ->schema([
                                        Forms\Components\TextInput::make('retail_price')
                                            ->label('سعر قطاعي')
                                            ->numeric()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                            ->required()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                $factor = $get('factor') ?? 1;
                                                $largeUnitId = $get('large_unit_id');

                                                // Auto-calculate large retail price if large unit exists
                                                if ($largeUnitId && $state !== null && $state !== '') {
                                                    $calculatedPrice = floatval($state) * intval($factor);
                                                    $set('large_retail_price', number_format($calculatedPrice, 2, '.', ''));
                                                }
                                            }),
                                        Forms\Components\TextInput::make('wholesale_price')
                                            ->label('سعر الجملة')
                                            ->numeric()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                            ->required()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                                $factor = $get('factor') ?? 1;
                                                $largeUnitId = $get('large_unit_id');

                                                // Auto-calculate large wholesale price if large unit exists
                                                if ($largeUnitId && $state !== null && $state !== '') {
                                                    $calculatedPrice = floatval($state) * intval($factor);
                                                    $set('large_wholesale_price', number_format($calculatedPrice, 2, '.', ''));
                                                }
                                            }),
                                        Forms\Components\TextInput::make('avg_cost')
                                            ->label('متوسط التكلفة')
                                            ->helperText('يتم حسابه تلقائياً من المتوسط المرجح لتكاليف المشتريات (للوحدة الصغيرة)')
                                            ->numeric()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                            ->default(0)
                                            ->disabled()
                                            ->dehydrated(),
                                    ])
                                    ->columns(3),

                                Forms\Components\Section::make('الأسعار - الوحدة الكبيرة')
                                    ->schema([
                                        Forms\Components\TextInput::make('large_retail_price')
                                            ->label('سعر قطاعي')
                                            ->helperText('يتم حسابه تلقائياً (سعر الوحدة الصغيرة × معامل التحويل)، يمكن تعديله يدوياً')
                                            ->numeric()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->nullable(),
                                        Forms\Components\TextInput::make('large_wholesale_price')
                                            ->label('سعر الجملة')
                                            ->helperText('يتم حسابه تلقائياً (سعر الوحدة الصغيرة × معامل التحويل)، يمكن تعديله يدوياً')
                                            ->numeric()
                                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->nullable(),
                                    ])
                                    ->columns(2)
                                    ->visible(fn (Forms\Get $get) => $get('large_unit_id') !== null),
                            ]),

                        Forms\Components\Tabs\Tab::make('المخزون')
                            ->icon('heroicon-m-cube')
                            ->schema([
                                Forms\Components\Grid::make(2)
                                    ->schema([
                                        Forms\Components\TextInput::make('sku')
                                            ->label('رمز المنتج')
                                            ->helperText('سيتم توليده تلقائياً إذا ترك فارغاً')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('barcode')
                                            ->label('الباركود (الوحدة الصغيرة)')
                                            ->helperText('سيتم توليده تلقائياً إذا ترك فارغاً')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('large_barcode')
                                            ->label('الباركود (الوحدة الكبيرة)')
                                            ->helperText('سيتم توليده تلقائياً إذا ترك فارغاً عند اختيار وحدة كبيرة')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255)
                                            ->visible(fn (Forms\Get $get) => $get('large_unit_id') !== null),
                                        Forms\Components\TextInput::make('min_stock')
                                            ->label('الحد الأدنى للمخزون')
                                            ->numeric()
                                            ->inputMode('decimal')
                                            ->extraInputAttributes(['dir' => 'ltr'])
                                            ->default(0)
                                            ->minValue(0)
                                            ->required(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['smallUnit', 'largeUnit'])
                ->withSum('stockMovements', 'quantity')
            )
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('الصورة')
                    ->circular()
                    ->disk('public')
                    ->defaultImageUrl(url('/images/placeholder-product.svg'))
                    ->size(40),
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('رمز المنتج')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('smallUnit.name')
                    ->label('الوحدة الصغيرة')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('largeUnit.name')
                    ->label('الوحدة الكبيرة')
                    ->sortable()
                    ->default('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wholesale_price')
                    ->label('سعر الجملة')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('retail_price')
                    ->label('سعر قطاعي')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('avg_cost')
                    ->label('متوسط التكلفة')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stock_movements_sum_quantity')
                    ->label('إجمالي المخزون')
                    ->sortable()
                    ->badge()
                    ->color(function ($state, Product $record) {
                        if ($state < 0) {
                            return 'danger';
                        }
                        if ($state < ($record->min_stock ?? 0)) {
                            return 'warning';
                        }

                        return 'success';
                    }),
                Tables\Columns\IconColumn::make('is_visible_in_retail_catalog')
                    ->label('كتالوج تجزئة')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_visible_in_wholesale_catalog')
                    ->label('كتالوج جملة')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('small_unit_id')
                    ->label('الوحدة الصغيرة')
                    ->relationship('smallUnit', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('stock_level')
                    ->label('مستوى المخزون')
                    ->form([
                        Forms\Components\Select::make('level')
                            ->label('المستوى')
                            ->options([
                                'out_of_stock' => 'نفذ من المخزون',
                                'low_stock' => 'مخزون منخفض',
                                'in_stock' => 'متوفر',
                            ])
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (! isset($data['level'])) {
                            return $query;
                        }

                        return $query->whereHas('stockMovements', function ($q) use ($data) {
                            $q->select('product_id')
                                ->selectRaw('SUM(quantity) as total_stock')
                                ->groupBy('product_id')
                                ->havingRaw(match ($data['level']) {
                                    'out_of_stock' => 'SUM(quantity) <= 0',
                                    'low_stock' => 'SUM(quantity) > 0 AND SUM(quantity) < products.min_stock',
                                    'in_stock' => 'SUM(quantity) > 0',
                                    default => '1=1'
                                });
                        });
                    }),
                Tables\Filters\Filter::make('price_range')
                    ->label('نطاق السعر')
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
                            ->when($data['from'], fn ($q, $price) => $q->where('retail_price', '>=', $price))
                            ->when($data['until'], fn ($q, $price) => $q->where('retail_price', '<=', $price));
                    }),
                Tables\Filters\Filter::make('has_large_unit')
                    ->label('وحدة كبيرة')
                    ->toggle()
                    ->query(fn ($query) => $query->whereNotNull('large_unit_id')),
                Tables\Filters\TernaryFilter::make('slow_moving')
                    ->label('الأصناف بطيئة الحركة')
                    ->placeholder('الكل')
                    ->trueLabel('بطيء الحركة (لا مبيعات منذ 90 يوم)')
                    ->falseLabel('نشط (له مبيعات حديثة)')
                    ->queries(
                        true: fn ($query) => $query->whereDoesntHave('salesInvoiceItems', function ($q) {
                            $q->whereHas('salesInvoice', function ($sq) {
                                $sq->where('status', 'posted')
                                    ->whereDate('created_at', '>=', now()->subDays(90))
                                    ->whereNull('deleted_at');
                            });
                        }),
                        false: fn ($query) => $query->whereHas('salesInvoiceItems', function ($q) {
                            $q->whereHas('salesInvoice', function ($sq) {
                                $sq->where('status', 'posted')
                                    ->whereDate('created_at', '>=', now()->subDays(90))
                                    ->whereNull('deleted_at');
                            });
                        }),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_stock_card')
                    ->label('كارت الصنف')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('info')
                    ->url(fn (Product $record) => route('filament.admin.pages.reports-hub', [
                        'report' => 'stock_card',
                        'product_id' => $record->id,
                    ]))
                    ->openUrlInNewTab(false)
                    ->visible(fn () => auth()->user()?->can('page_StockCard')),
                Tables\Actions\ReplicateAction::make()
                    ->excludeAttributes(['stock_movements_sum_quantity'])
                    ->beforeReplicaSaved(function ($replica) {
                        // Clear unique fields
                        $replica->barcode = null;
                        $replica->large_barcode = null;
                        $replica->sku = null;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Product $record, Tables\Actions\DeleteAction $action) {
                        // Check for related records to prevent deletion
                        $hasStockMovements = \App\Models\StockMovement::where('product_id', $record->id)->exists();
                        $hasSalesInvoiceItems = \App\Models\SalesInvoiceItem::where('product_id', $record->id)->exists();
                        $hasPurchaseInvoiceItems = \App\Models\PurchaseInvoiceItem::where('product_id', $record->id)->exists();
                        $hasQuotationItems = class_exists('\App\Models\QuotationItem')
                            && \App\Models\QuotationItem::where('product_id', $record->id)->exists();

                        if ($hasStockMovements || $hasSalesInvoiceItems || $hasPurchaseInvoiceItems || $hasQuotationItems) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('فشل حذف المنتج')
                                ->body('لا يمكن حذف المنتج لوجود فواتير أو حركات مخزون أو عروض أسعار مرتبطة به')
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->successNotification(
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('تم حذف المنتج بنجاح')
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('bulk_price_update')
                        ->label('تحديث الأسعار')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->modalHeading('تحديث أسعار المنتجات المحددة')
                        ->modalWidth('md')
                        ->form([
                            Forms\Components\Select::make('update_type')
                                ->label('نوع التحديث')
                                ->options([
                                    'percentage_increase' => 'زيادة بنسبة مئوية',
                                    'percentage_decrease' => 'تخفيض بنسبة مئوية',
                                    'fixed_increase' => 'زيادة مبلغ ثابت',
                                    'fixed_decrease' => 'تخفيض مبلغ ثابت',
                                    'set_price' => 'تحديد سعر ثابت',
                                ])
                                ->required()
                                ->native(false)
                                ->reactive(),

                            Forms\Components\TextInput::make('value')
                                ->label(function ($get) {
                                    return match ($get('update_type')) {
                                        'percentage_increase', 'percentage_decrease' => 'النسبة المئوية (%)',
                                        'fixed_increase', 'fixed_decrease' => 'المبلغ (ج.م)',
                                        'set_price' => 'السعر الجديد (ج.م)',
                                        default => 'القيمة',
                                    };
                                })
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                ->step(0.01),

                            Forms\Components\Select::make('price_field')
                                ->label('الحقل المراد تحديثه')
                                ->options([
                                    'retail_price' => 'سعر قطاعي (صغير)',
                                    'wholesale_price' => 'سعر الجملة (صغير)',
                                    'large_retail_price' => 'سعر قطاعي (كبير)',
                                    'large_wholesale_price' => 'سعر الجملة (كبير)',
                                ])
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $updated = 0;

                            foreach ($records as $product) {
                                $currentPrice = floatval($product->{$data['price_field']} ?? 0);

                                $newPrice = match ($data['update_type']) {
                                    'percentage_increase' => $currentPrice * (1 + $data['value'] / 100),
                                    'percentage_decrease' => $currentPrice * (1 - $data['value'] / 100),
                                    'fixed_increase' => $currentPrice + $data['value'],
                                    'fixed_decrease' => $currentPrice - $data['value'],
                                    'set_price' => $data['value'],
                                };

                                // Prevent negative prices
                                $newPrice = max(0, $newPrice);

                                $product->update([
                                    $data['price_field'] => $newPrice,
                                ]);
                                $updated++;
                            }

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('تم تحديث الأسعار بنجاح')
                                ->body("تم تحديث {$updated} منتج")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $deleted = 0;
                            $failed = 0;
                            $failedProducts = [];

                            foreach ($records as $record) {
                                // Check for related records
                                $hasStockMovements = \App\Models\StockMovement::where('product_id', $record->id)->exists();
                                $hasSalesInvoiceItems = \App\Models\SalesInvoiceItem::where('product_id', $record->id)->exists();
                                $hasPurchaseInvoiceItems = \App\Models\PurchaseInvoiceItem::where('product_id', $record->id)->exists();
                                $hasQuotationItems = class_exists('\App\Models\QuotationItem')
                                    && \App\Models\QuotationItem::where('product_id', $record->id)->exists();

                                if ($hasStockMovements || $hasSalesInvoiceItems || $hasPurchaseInvoiceItems || $hasQuotationItems) {
                                    $failed++;
                                    $failedProducts[] = $record->name;
                                } else {
                                    $record->delete();
                                    $deleted++;
                                }
                            }

                            if ($deleted > 0 && $failed === 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('تم حذف المنتجات بنجاح')
                                    ->body("تم حذف {$deleted} منتج بنجاح")
                                    ->send();
                            } elseif ($deleted > 0 && $failed > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('تم حذف بعض المنتجات')
                                    ->body("تم حذف {$deleted} منتج، وفشل حذف {$failed} منتج لوجود بيانات مرتبطة")
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->danger()
                                    ->title('فشل حذف المنتجات')
                                    ->body('لا يمكن حذف المنتجات المحددة لوجود فواتير أو حركات مخزون أو عروض أسعار مرتبطة بها')
                                    ->send();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('معلومات أساسية')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('اسم المنتج'),
                        Infolists\Components\TextEntry::make('barcode')
                            ->label('الباركود (الوحدة الصغيرة)'),
                        Infolists\Components\TextEntry::make('large_barcode')
                            ->label('الباركود (الوحدة الكبيرة)')
                            ->visible(fn ($record) => $record->large_barcode !== null),
                        Infolists\Components\TextEntry::make('sku')
                            ->label('رمز المنتج'),
                        Infolists\Components\TextEntry::make('min_stock')
                            ->label('الحد الأدنى للمخزون')
                            ->numeric(),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('الصور')
                    ->schema([
                        Infolists\Components\ImageEntry::make('image')
                            ->label('الصورة الرئيسية')
                            ->disk('public')
                            ->columnSpanFull(),
                        Infolists\Components\TextEntry::make('images')
                            ->label('صور إضافية')
                            ->formatStateUsing(function ($state, $record) {
                                if (empty($state) || ! is_array($state)) {
                                    return null;
                                }

                                $disk = \Illuminate\Support\Facades\Storage::disk('public');
                                $html = '<div class="flex flex-wrap gap-4">';
                                foreach ($state as $image) {
                                    $url = $disk->url($image);
                                    $html .= '<img src="'.$url.'" alt="صورة إضافية" class="w-32 h-32 object-cover rounded-lg">';
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->visible(fn ($record) => ! empty($record->images) && is_array($record->images))
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Infolists\Components\Section::make('نظام الوحدات المزدوج')
                    ->schema([
                        Infolists\Components\TextEntry::make('smallUnit.name')
                            ->label('الوحدة الصغيرة (الأساسية)'),
                        Infolists\Components\TextEntry::make('largeUnit.name')
                            ->label('الوحدة الكبيرة (الكرتون)')
                            ->visible(fn ($record) => $record->large_unit_id !== null),
                        Infolists\Components\TextEntry::make('factor')
                            ->label('معامل التحويل')
                            ->visible(fn ($record) => $record->large_unit_id !== null),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('الأسعار - الوحدة الصغيرة')
                    ->schema([
                        Infolists\Components\TextEntry::make('retail_price')
                            ->label('سعر قطاعي')
                            ->numeric(decimalPlaces: 2),
                        Infolists\Components\TextEntry::make('wholesale_price')
                            ->label('سعر الجملة')
                            ->numeric(decimalPlaces: 2),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('الأسعار - الوحدة الكبيرة')
                    ->schema([
                        Infolists\Components\TextEntry::make('large_retail_price')
                            ->label('سعر قطاعي')
                            ->numeric(decimalPlaces: 2),
                        Infolists\Components\TextEntry::make('large_wholesale_price')
                            ->label('سعر الجملة')
                            ->numeric(decimalPlaces: 2),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => $record->large_unit_id !== null),

                Infolists\Components\Section::make('معلومات إضافية')
                    ->schema([
                        Infolists\Components\TextEntry::make('avg_cost')
                            ->label('متوسط التكلفة')
                            ->numeric(decimalPlaces: 4),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
