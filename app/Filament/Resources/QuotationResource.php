<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Filament\Resources\QuotationResource\RelationManagers;
use App\Models\Quotation;
use App\Models\Product;
use App\Settings\CompanySettings;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'عروض الأسعار';

    protected static ?string $modelLabel = 'عرض سعر';

    protected static ?string $pluralModelLabel = 'عروض الأسعار';

    protected static ?string $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'quotation_number';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'draft')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->quotation_number;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'العميل' => $record->customer_name,
            'الإجمالي' => number_format($record->total, 2) . ' ج.م',
            'الحالة' => match($record->status) {
                'draft' => 'مسودة',
                'sent' => 'مرسل',
                'accepted' => 'مقبول',
                'converted' => 'محول',
                'rejected' => 'مرفوض',
                'expired' => 'منتهي',
                default => $record->status,
            },
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['quotation_number'];
    }

    public static function form(Form $form): Form
    {
        $companySettings = app(CompanySettings::class);

        return $form
            ->schema([
                // Section 1: Customer Information
                Forms\Components\Section::make('معلومات العميل')
                    ->schema([
                        Forms\Components\Toggle::make('is_guest')
                            ->label('عميل غير مسجل')
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $set('partner_id', null);
                                } else {
                                    $set('guest_name', null);
                                    $set('guest_phone', null);
                                }
                            })
                            ->dehydrated(false)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('partner_id')
                            ->label('العميل')
                            ->relationship(
                                'partner',
                                'name',
                                fn (Builder $query) => $query->where('type', 'customer')->where('is_banned', false)
                            )
                            ->searchable(['name', 'phone'])
                            ->preload()
                            ->required(fn (Get $get) => !$get('is_guest'))
                            ->hidden(fn (Get $get) => $get('is_guest'))
                            ->native(false)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('guest_name')
                            ->label('اسم العميل')
                            ->visible(fn (Get $get) => $get('is_guest'))
                            ->required(fn (Get $get) => $get('is_guest'))
                            ->maxLength(255)
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('guest_phone')
                            ->label('هاتف العميل')
                            ->visible(fn (Get $get) => $get('is_guest'))
                            ->required(fn (Get $get) => $get('is_guest'))
                            ->tel()
                            ->maxLength(20)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                // Section 2: Quotation Settings
                Forms\Components\Section::make('إعدادات عرض السعر')
                    ->schema([
                        Forms\Components\Select::make('pricing_type')
                            ->label('نوع التسعير')
                            ->options([
                                'retail' => 'سعر التجزئة',
                                'wholesale' => 'سعر الجملة',
                                'manual' => 'يدوي',
                            ])
                            ->default('retail')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $items = $get('items');
                                if (!$items) return;

                                foreach ($items as $key => $item) {
                                    if (!isset($item['product_id'])) continue;

                                    $product = Product::find($item['product_id']);
                                    if (!$product) continue;

                                    $unitType = $item['unit_type'] ?? 'small';
                                    $price = match ($state) {
                                        'retail' => $unitType === 'small' ? $product->retail_price : $product->large_retail_price,
                                        'wholesale' => $unitType === 'small' ? $product->wholesale_price : $product->large_wholesale_price,
                                        'manual' => $item['unit_price'] ?? 0,
                                    };

                                    $set("items.{$key}.unit_price", $price);
                                    $quantity = $item['quantity'] ?? 1;
                                    $set("items.{$key}.total", $price * $quantity);
                                }

                                static::recalculateTotals($set, $get);
                            })
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('تاريخ انتهاء الصلاحية')
                            ->native(false)
                            ->minDate(now()->addDay())
                            ->nullable()
                            ->displayFormat('Y-m-d')
                            ->columnSpan(1),

                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'draft' => 'مسودة',
                                'sent' => 'مرسل',
                                'accepted' => 'مقبول',
                                'converted' => 'محول',
                                'rejected' => 'مرفوض',
                                'expired' => 'منتهي الصلاحية',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false)
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات (مرئية للعميل)')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('internal_notes')
                            ->label('ملاحظات داخلية (للإدارة فقط)')
                            ->rows(3)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                // Section 3: Items
                Forms\Components\Section::make('الأصناف')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->label('')
                            ->relationship('items')
                            ->addActionLabel('إضافة صنف')
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('الصنف')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->searchable(['name', 'barcode', 'sku'])
                                    ->getOptionLabelFromRecordUsing(fn (Product $record) => "{$record->name} - {$record->barcode}")
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            $product = Product::find($state);
                                            if ($product) {
                                                $pricingType = $get('../../pricing_type') ?? 'retail';
                                                $unitType = $get('unit_type') ?? 'small';

                                                $price = match ($pricingType) {
                                                    'retail' => $unitType === 'small' ? $product->retail_price : $product->large_retail_price,
                                                    'wholesale' => $unitType === 'small' ? $product->wholesale_price : $product->large_wholesale_price,
                                                    default => 0,
                                                };

                                                $set('unit_price', $price);
                                                $set('quantity', 1);
                                                $set('total', $price);

                                                // Set snapshot data
                                                $set('product_name', $product->name);
                                                if ($unitType === 'small') {
                                                    $set('unit_name', $product->smallUnit->name ?? 'وحدة');
                                                } else {
                                                    $set('unit_name', $product->largeUnit->name ?? 'وحدة كبيرة');
                                                }
                                            }
                                        }
                                    })
                                    ->columnSpan(4)
                                    ->native(false),

                                Forms\Components\Select::make('unit_type')
                                    ->label('الوحدة')
                                    ->options(function (Get $get) {
                                        $productId = $get('product_id');
                                        if (!$productId) {
                                            return ['small' => 'صغيرة'];
                                        }
                                        $product = Product::find($productId);
                                        $options = ['small' => $product?->smallUnit?->name ?? 'صغيرة'];
                                        if ($product && $product->large_unit_id) {
                                            $options['large'] = $product->largeUnit->name ?? 'كبيرة';
                                        }
                                        return $options;
                                    })
                                    ->default('small')
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $productId = $get('product_id');
                                        if ($productId && $state) {
                                            $product = Product::find($productId);
                                            if ($product) {
                                                $pricingType = $get('../../pricing_type') ?? 'retail';
                                                $price = match ($pricingType) {
                                                    'retail' => $state === 'small' ? $product->retail_price : $product->large_retail_price,
                                                    'wholesale' => $state === 'small' ? $product->wholesale_price : $product->large_wholesale_price,
                                                    'manual' => $get('unit_price') ?? 0,
                                                };

                                                $set('unit_price', $price);
                                                $quantity = $get('quantity') ?? 1;
                                                $set('total', $price * $quantity);

                                                // Update snapshot unit name
                                                if ($state === 'small') {
                                                    $set('unit_name', $product->smallUnit->name ?? 'وحدة');
                                                } else {
                                                    $set('unit_name', $product->largeUnit->name ?? 'وحدة كبيرة');
                                                }
                                            }
                                        }
                                    })
                                    ->columnSpan(2)
                                    ->native(false),

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
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
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
                                    ->suffix($companySettings->currency_symbol)
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->disabled()
                                    ->dehydrated()
                                    ->suffix($companySettings->currency_symbol)
                                    ->columnSpan(2),

                                // Hidden snapshot fields
                                Forms\Components\Hidden::make('product_name'),
                                Forms\Components\Hidden::make('unit_name'),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['product_id'] ? Product::find($state['product_id'])?->name : null)
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                static::recalculateTotals($set, $get);
                            })
                            ->deleteAction(
                                fn (Forms\Components\Actions\Action $action) => $action->after(fn (Set $set, Get $get) => static::recalculateTotals($set, $get))
                            ),
                    ])
                    ->collapsible(),

                // Section 4: Totals
                Forms\Components\Section::make('الإجماليات')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('المجموع الفرعي')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->suffix($companySettings->currency_symbol),

                        Forms\Components\TextInput::make('discount')
                            ->label('الخصم')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->default(0)
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->suffix($companySettings->currency_symbol),

                        Forms\Components\TextInput::make('total')
                            ->label('الإجمالي النهائي')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->suffix($companySettings->currency_symbol),
                    ])
                    ->columns(3)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    protected static function recalculateTotals(Set $set, Get $get): void
    {
        $items = $get('items') ?? [];
        $subtotal = collect($items)->sum('total');

        $set('subtotal', $subtotal);
        $set('total', $subtotal); // Discount not implemented yet
    }

    public static function table(Table $table): Table
    {
        $companySettings = app(CompanySettings::class);

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('quotation_number')
                    ->label('رقم العرض')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('تم نسخ رقم العرض'),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('العميل')
                    ->searchable(['partner.name', 'guest_name'])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->orderByRaw("COALESCE(partners.name, quotations.guest_name) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('الهاتف')
                    ->searchable(['partner.phone', 'guest_phone'])
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'secondary' => 'draft',
                        'info' => 'sent',
                        'success' => 'accepted',
                        'warning' => 'converted',
                        'danger' => 'rejected',
                        'gray' => 'expired',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'مسودة',
                        'sent' => 'مرسل',
                        'accepted' => 'مقبول',
                        'converted' => 'محول',
                        'rejected' => 'مرفوض',
                        'expired' => 'منتهي',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->money($companySettings->currency, locale: 'ar')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('صالح حتى')
                    ->date('Y-m-d')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->isExpired() ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state, $record) => $record->isExpired() ? 'منتهي الصلاحية' : $state?->format('Y-m-d'))
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('أنشئ بواسطة')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'sent' => 'مرسل',
                        'accepted' => 'مقبول',
                        'converted' => 'محول',
                        'rejected' => 'مرفوض',
                        'expired' => 'منتهي',
                    ])
                    ->native(false),

                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('الشريك')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\Filter::make('valid')
                    ->label('صالح')
                    ->query(fn (Builder $query): Builder => $query->valid()),

                Tables\Filters\Filter::make('expired')
                    ->label('منتهي')
                    ->query(fn (Builder $query): Builder => $query->expired()),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Quotation $record) => $record->canBeEdited()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Quotation $record) => $record->status === 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
