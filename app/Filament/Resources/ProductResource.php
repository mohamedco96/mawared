<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'المنتجات';

    protected static ?string $modelLabel = 'منتج';

    protected static ?string $pluralModelLabel = 'المنتجات';

    protected static ?string $navigationGroup = 'إدارة المخزون';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات أساسية')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم المنتج')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->columnSpanFull(),
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
                        Forms\Components\TextInput::make('sku')
                            ->label('رمز المنتج')
                            ->helperText('سيتم توليده تلقائياً إذا ترك فارغاً')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('min_stock')
                            ->label('الحد الأدنى للمخزون')
                            ->numeric()
                            ->inputMode('decimal')
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('نظام الوحدات المزدوج')
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
                            ->label('سعر التجزئة')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->required()
                            ->step(0.01)
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
                            ->default(0)
                            ->required()
                            ->step(0.01)
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
                    ])
                    ->columns(2),

                Forms\Components\Section::make('الأسعار - الوحدة الكبيرة')
                    ->schema([
                        Forms\Components\TextInput::make('large_retail_price')
                            ->label('سعر التجزئة')
                            ->helperText('يتم حسابه تلقائياً (سعر الوحدة الصغيرة × معامل التحويل)، يمكن تعديله يدوياً')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01)
                            ->nullable(),
                        Forms\Components\TextInput::make('large_wholesale_price')
                            ->label('سعر الجملة')
                            ->helperText('يتم حسابه تلقائياً (سعر الوحدة الصغيرة × معامل التحويل)، يمكن تعديله يدوياً')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01)
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('large_unit_id') !== null),

                Forms\Components\Section::make('معلومات إضافية')
                    ->schema([
                        Forms\Components\TextInput::make('avg_cost')
                            ->label('متوسط التكلفة')
                            ->helperText('يتم حسابه تلقائياً من المتوسط المرجح لتكاليف المشتريات (للوحدة الصغيرة)')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('barcode')
                    ->label('الباركود')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('رمز المنتج')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('smallUnit.name')
                    ->label('الوحدة الصغيرة')
                    ->sortable(),
                Tables\Columns\TextColumn::make('largeUnit.name')
                    ->label('الوحدة الكبيرة')
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('retail_price')
                    ->label('سعر التجزئة')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('avg_cost')
                    ->label('متوسط التكلفة')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_stock')
                    ->label('إجمالي المخزون')
                    ->getStateUsing(function (Product $record) {
                        // Get total stock across all warehouses
                        return DB::table('stock_movements')
                            ->where('product_id', $record->id)
                            ->sum('quantity') ?? 0;
                    })
                    ->sortable()
                    ->badge()
                    ->color(function ($state, Product $record) {
                        if ($state < 0) return 'danger';
                        if ($state < ($record->min_stock ?? 0)) return 'warning';
                        return 'success';
                    }),
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
                        if (!isset($data['level'])) {
                            return $query;
                        }

                        return $query->whereHas('stockMovements', function ($q) use ($data) {
                            $q->select('product_id')
                                ->selectRaw('SUM(quantity) as total_stock')
                                ->groupBy('product_id')
                                ->havingRaw(match($data['level']) {
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ReplicateAction::make()
                    ->beforeReplicaSaved(function ($replica) {
                        // Clear unique fields
                        $replica->barcode = null;
                        $replica->large_barcode = null;
                        $replica->sku = null;
                    }),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
