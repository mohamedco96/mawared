<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesReturnResource\Pages;
use App\Models\Product;
use App\Models\SalesReturn;
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
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesReturnResource extends Resource
{
    protected static ?string $model = SalesReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'مرتجعات المبيعات';

    protected static ?string $modelLabel = 'مرتجع مبيعات';

    protected static ?string $pluralModelLabel = 'مرتجعات المبيعات';

    protected static ?string $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('معلومات المرتجع')
                            ->schema([
                                Forms\Components\TextInput::make('return_number')
                                    ->label('رقم المرتجع')
                                    ->default(fn () => 'RET-SALE-'.now()->format('Ymd').'-'.Str::random(6))
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
                                    ->live()
                                    ->rules([
                                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if ($value === 'posted') {
                                                $items = $get('items');
                                                if (empty($items)) {
                                                    $fail('لا يمكن تأكيد المرتجع بدون أصناف.');
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
                                    ->default(fn () => Warehouse::where('is_active', true)->first()?->id ?? Warehouse::first()?->id)
                                    ->reactive()
                                    ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Forms\Components\Select::make('partner_id')
                                    ->label('العميل')
                                    ->relationship('partner', 'name', fn ($query) => $query->where('type', 'customer'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('sales_invoice_id', null);
                                        $set('items', []);
                                    })
                                    ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                Forms\Components\Select::make('sales_invoice_id')
                                    ->label('فاتورة البيع')
                                    ->relationship('salesInvoice', 'invoice_number',
                                        fn ($query, Get $get) => $query
                                            ->where('partner_id', $get('partner_id'))
                                            ->where('status', 'posted')
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if ($state) {
                                            $invoice = \App\Models\SalesInvoice::with('items.product')->find($state);
                                            if ($invoice) {
                                                if ($invoice->isFullyReturned()) {
                                                    Notification::make()
                                                        ->danger()
                                                        ->title('لا يمكن إنشاء مرتجع')
                                                        ->body('هذه الفاتورة تم إرجاعها بالكامل')
                                                        ->send();
                                                    $set('sales_invoice_id', null);
                                                    $set('items', []);

                                                    return;
                                                }
                                                $items = $invoice->items->map(function ($item) use ($invoice) {
                                                    $availableQty = $invoice->getAvailableReturnQuantity($item->product_id, $item->unit_type);

                                                    return [
                                                        'product_id' => $item->product_id,
                                                        'unit_type' => $item->unit_type,
                                                        'quantity' => min(1, $availableQty),
                                                        'unit_price' => $item->net_unit_price,
                                                        'discount' => 0,
                                                        'total' => $item->net_unit_price * min(1, $availableQty),
                                                        'max_quantity' => $availableQty,
                                                        'original_quantity' => $item->quantity,
                                                    ];
                                                })->filter(fn ($item) => $item['max_quantity'] > 0)->toArray();

                                                if (empty($items)) {
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('تحذير')
                                                        ->body('جميع أصناف هذه الفاتورة تم إرجاعها بالكامل')
                                                        ->send();
                                                    $set('sales_invoice_id', null);
                                                }
                                                $set('items', $items);
                                                self::calculateTotals($items, $set, $get('discount') ?? 0);
                                            }
                                        } else {
                                            $set('items', []);
                                            self::calculateTotals([], $set, 0);
                                        }
                                    })
                                    ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                    ->visible(fn (Get $get) => $get('partner_id') !== null)
                                    ->helperText('اختياري: اختر فاتورة لتحميل أصنافها تلقائياً'),
                                Forms\Components\Hidden::make('payment_method')
                                    ->default('cash')
                                    ->dehydrated(),
                            ])
                            ->columns(2)
                            ->columnSpan(2),

                        Forms\Components\Section::make('بيانات العميل')
                            ->schema([
                                Forms\Components\Placeholder::make('partner_card')
                                    ->label('')
                                    ->content(function (Get $get) {
                                        $partnerId = $get('partner_id');
                                        if (! $partnerId) {
                                            return new \Illuminate\Support\HtmlString('<div class="text-gray-500">اختر عميلاً لعرض بياناته</div>');
                                        }
                                        $partner = \App\Models\Partner::find($partnerId);

                                        return view('filament.components.partner-card', ['partner' => $partner]);
                                    }),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Forms\Components\Section::make('إضافة منتج')
                    ->schema([
                        Forms\Components\Select::make('product_scanner')
                            ->label('مسح الباركود أو البحث عن منتج')
                            ->searchable(['name', 'barcode', 'sku'])
                            ->options(function () {
                                return Product::latest()->limit(20)->pluck('name', 'id');
                            })
                            ->getSearchResultsUsing(function (string $search) {
                                return Product::query()
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")
                                    ->orWhere('barcode', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->pluck('name', 'id');
                            })
                            ->getOptionLabelUsing(fn ($value) => Product::find($value)?->name)
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if (! $state) {
                                    return;
                                }
                                $product = Product::find($state);
                                if (! $product) {
                                    return;
                                }

                                $items = $get('items') ?? [];
                                $found = false;

                                foreach ($items as &$item) {
                                    if ($item['product_id'] == $product->id && ($item['unit_type'] ?? 'small') == 'small') {
                                        $item['quantity']++;
                                        $item['total'] = $item['quantity'] * $item['unit_price'];
                                        $found = true;
                                        break;
                                    }
                                }

                                if (! $found) {
                                    $items[] = [
                                        'product_id' => $product->id,
                                        'unit_type' => 'small',
                                        'quantity' => 1,
                                        'unit_price' => $product->retail_price,
                                        'discount' => 0,
                                        'total' => $product->retail_price,
                                        'max_quantity' => null,
                                    ];
                                }

                                $set('items', $items);
                                $set('product_scanner', null);
                                self::calculateTotals($items, $set, $get('discount') ?? 0);
                            })
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get) => ! $get('sales_invoice_id')),

                Forms\Components\Section::make('أصناف المرتجع')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->addable(false)
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('المنتج')
                                    ->relationship('product', 'name')
                                    ->required()
                                    ->disabled()
                                    ->dehydrated()
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
                                    ->live()
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
                                    })
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->integer()
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitPrice = floatval($get('unit_price') ?? 0);
                                        $quantity = intval($state);
                                        $set('total', $unitPrice * $quantity);
                                    })
                                    ->rules([
                                        'required',
                                        'integer',
                                        'min:1',
                                        fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if ($value !== null && intval($value) <= 0) {
                                                $fail('الكمية يجب أن تكون أكبر من صفر.');
                                            }
                                            $maxQty = $get('max_quantity');
                                            if ($maxQty !== null && intval($value) > $maxQty) {
                                                $fail("الكمية المتاحة للإرجاع هي {$maxQty} فقط.");
                                            }
                                        },
                                    ])
                                    ->helperText(fn (Get $get) => $get('max_quantity') ? "الكمية المتاحة: {$get('max_quantity')}" : null)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('سعر الوحدة')
                                    ->numeric()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = intval($get('quantity') ?? 1);
                                        $unitPrice = floatval($state);
                                        $set('total', $unitPrice * $quantity);
                                    })
                                    ->disabled(fn (Get $get) => $get('../../sales_invoice_id') !== null)
                                    ->dehydrated()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated()
                                    ->columnSpan(2),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::calculateTotals($get('items') ?? [], $set, $get('discount') ?? 0);
                            })
                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                    ]),

                Forms\Components\Section::make('ملخص المرتجع')
                    ->schema([
                        Forms\Components\Grid::make(12)
                            ->schema([
                                // Summary Section (Full Width or Right Aligned)
                                Forms\Components\Group::make()
                                    ->columnSpan(12)
                                    ->schema([
                                        Forms\Components\Section::make()
                                            ->columns(4)
                                            ->schema([
                                                Forms\Components\TextInput::make('subtotal')
                                                    ->label('المجموع الفرعي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->prefix('ج.م')
                                                    ->columnSpan(1),

                                                Forms\Components\TextInput::make('discount')
                                                    ->label('إجمالي الخصم')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                        self::calculateTotals($get('items') ?? [], $set, floatval($state));
                                                    })
                                                    ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord)
                                                    ->columnSpan(1),

                                                Forms\Components\TextInput::make('total')
                                                    ->label('الإجمالي النهائي')
                                                    ->numeric()
                                                    ->readOnly()
                                                    ->prefix('ج.م')
                                                    ->extraInputAttributes(['style' => 'font-size: 1.5rem; font-weight: bold; color: #16a34a; text-align: center'])
                                                    ->columnSpan(2),
                                            ]),
                                    ]),

                                // Notes Section (Bottom)
                                Forms\Components\Section::make('ملاحظات إضافية')
                                    ->columnSpan(12)
                                    ->schema([
                                        Forms\Components\Textarea::make('notes')
                                            ->hiddenLabel()
                                            ->placeholder('أدخل أي ملاحظات إضافية هنا...')
                                            ->rows(3)
                                            ->disabled(fn ($record, $livewire) => $record && $record->isPosted() && $livewire instanceof \Filament\Resources\Pages\EditRecord),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function calculateTotals(array $items, Set $set, float $discount = 0): void
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += floatval($item['total'] ?? 0);
        }
        $set('subtotal', number_format($subtotal, 2, '.', ''));
        $set('total', number_format($subtotal - $discount, 2, '.', ''));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['partner', 'warehouse', 'salesInvoice']))
            ->columns([
                Tables\Columns\TextColumn::make('return_number')
                    ->label('رقم المرتجع')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('salesInvoice.invoice_number')
                    ->label('فاتورة البيع')
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
                    ->label('العميل')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('sales_invoice_id')
                    ->label('مرتبط بفاتورة')
                    ->placeholder('الكل')
                    ->trueLabel('مرتبط بفاتورة')
                    ->falseLabel('غير مرتبط بفاتورة')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('sales_invoice_id'),
                        false: fn ($query) => $query->whereNull('sales_invoice_id'),
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
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (SalesReturn $record) {
                        if ($record->items()->count() === 0) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن تأكيد المرتجع')
                                ->body('المرتجع لا يحتوي على أي أصناف')
                                ->send();

                            return;
                        }

                        try {
                            $stockService = app(StockService::class);
                            $treasuryService = app(TreasuryService::class);

                            DB::transaction(function () use ($record, $stockService, $treasuryService) {
                                $stockService->postSalesReturn($record);
                                $treasuryService->postSalesReturn($record);
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
                    ->visible(fn (SalesReturn $record) => $record->isDraft()),
                Tables\Actions\EditAction::make()
                    ->visible(fn (SalesReturn $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, SalesReturn $record) {
                        if ($record->hasAssociatedRecords()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن الحذف')
                                ->body('لا يمكن حذف المرتجع لأنه مؤكد أو له حركات مالية مرتبطة.')
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $skippedCount = 0;
                            $deletedCount = 0;

                            $records->each(function (SalesReturn $record) use (&$skippedCount, &$deletedCount) {
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
                                    ->body("تم حذف {$deletedCount} مرتجع")
                                    ->send();
                            }

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('تم تخطي بعض المرتجعات')
                                    ->body("لم يتم حذف {$skippedCount} مرتجع لكونها مؤكدة أو لها حركات مرتبطة")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesReturns::route('/'),
            'create' => Pages\CreateSalesReturn::route('/create'),
            'edit' => Pages\EditSalesReturn::route('/{record}/edit'),
        ];
    }
}
