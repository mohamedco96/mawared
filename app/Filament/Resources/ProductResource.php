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
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('barcode')
                            ->label('الباركود')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('sku')
                            ->label('رمز المنتج')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('min_stock')
                            ->label('الحد الأدنى للمخزون')
                            ->numeric()
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
                            ]),
                        Forms\Components\TextInput::make('factor')
                            ->label('معامل التحويل')
                            ->helperText('عدد الوحدات الصغيرة في الوحدة الكبيرة')
                            ->numeric()
                            ->default(1)
                            ->required()
                            ->minValue(1),
                    ])
                    ->columns(3),
                
                Forms\Components\Section::make('الأسعار - الوحدة الصغيرة')
                    ->schema([
                        Forms\Components\TextInput::make('retail_price')
                            ->label('سعر التجزئة')
                            ->numeric()
                            ->prefix('ر.س')
                            ->default(0)
                            ->required()
                            ->step(0.0001),
                        Forms\Components\TextInput::make('wholesale_price')
                            ->label('سعر الجملة')
                            ->numeric()
                            ->prefix('ر.س')
                            ->default(0)
                            ->required()
                            ->step(0.0001),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('الأسعار - الوحدة الكبيرة')
                    ->schema([
                        Forms\Components\TextInput::make('large_retail_price')
                            ->label('سعر التجزئة')
                            ->numeric()
                            ->prefix('ر.س')
                            ->step(0.0001)
                            ->nullable(),
                        Forms\Components\TextInput::make('large_wholesale_price')
                            ->label('سعر الجملة')
                            ->numeric()
                            ->prefix('ر.س')
                            ->step(0.0001)
                            ->nullable(),
                    ])
                    ->columns(2)
                    ->visible(fn (Forms\Get $get) => $get('large_unit_id') !== null),
                
                Forms\Components\Section::make('معلومات إضافية')
                    ->schema([
                        Forms\Components\TextInput::make('avg_cost')
                            ->label('متوسط التكلفة')
                            ->numeric()
                            ->prefix('ر.س')
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
                    ->money('SAR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('avg_cost')
                    ->label('متوسط التكلفة')
                    ->money('SAR')
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
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
