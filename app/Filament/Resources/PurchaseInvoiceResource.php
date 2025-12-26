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

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'فواتير الشراء';

    protected static ?string $modelLabel = 'فاتورة شراء';

    protected static ?string $pluralModelLabel = 'فواتير الشراء';

    protected static ?string $navigationGroup = 'المشتريات';

    protected static ?int $navigationSort = 1;

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
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('warehouse_id')
                            ->label('المخزن')
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('partner_id')
                            ->label('المورد')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'supplier'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->autofocus()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
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
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('أصناف الفاتورة')
                    ->schema([
                        Forms\Components\Repeater::make('items')
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
                                                $set('discount', 0);
                                                $set('total', $product->avg_cost ?: 0);
                                            }
                                        }
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\Select::make('unit_type')
                                    ->label('نوع الوحدة')
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
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitCost = $get('unit_cost') ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($unitCost * $state) - $discount);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('تكلفة الوحدة')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->required()
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($state * $quantity) - $discount);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('discount')
                                    ->label('خصم')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->default(0)
                                    ->step(0.01)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitCost = $get('unit_cost') ?? 0;
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total', ($unitCost * $quantity) - $state);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
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
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(7)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['product_id'] ? Product::find($state['product_id'])?->name : null)
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                static::recalculateTotals($set, $get);
                            })
                            ->disabled(fn ($record) => $record && $record->isPosted()),
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
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\TextInput::make('discount_value')
                            ->label(function (Get $get) {
                                return $get('discount_type') === 'percentage'
                                    ? 'نسبة الخصم (%)'
                                    : 'قيمة الخصم';
                            })
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(function (Get $get) {
                                return $get('discount_type') === 'percentage' ? 100 : null;
                            })
                            ->suffix(fn (Get $get) => $get('discount_type') === 'percentage' ? '%' : '')
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                static::recalculateTotals($set, $get);
                            })
                            ->disabled(fn ($record) => $record && $record->isPosted()),
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
                            ->step(0.01)
                            ->minValue(0)
                            ->reactive()
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
                            ->helperText('يتم ملؤه تلقائياً حسب طريقة الدفع أو يمكن تعديله يدوياً')
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\TextInput::make('remaining_amount')
                            ->label('المبلغ المتبقي')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->default(0)
                            ->disabled()
                            ->dehydrated(),
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
                    ->disabled(fn ($record) => $record && $record->isPosted()),
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
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn (string $state): string => $state === 'draft' ? 'مسودة' : 'مؤكدة')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'draft' ? 'warning' : 'success'),
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

                                // Update product prices if new_selling_price is set
                                foreach ($record->items as $item) {
                                    if ($item->new_selling_price !== null || $item->new_large_selling_price !== null) {
                                        $stockService->updateProductPrice(
                                            $item->product,
                                            $item->new_selling_price,
                                            $item->unit_type,
                                            $item->new_large_selling_price
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
                                    ->step(0.01),

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
                        ($record->current_remaining ?? $record->remaining_amount) > 0
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
