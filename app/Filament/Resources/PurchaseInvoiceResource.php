<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Models\Product;
use App\Models\PurchaseInvoice;
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
                            ->maxLength(255),
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
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('payment_method')
                            ->label('طريقة الدفع')
                            ->options([
                                'cash' => 'نقدي',
                                'credit' => 'آجل',
                            ])
                            ->default('cash')
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('أصناف الفاتورة')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
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
                                    ->required()
                                    ->default(1)
                                    ->minValue(1)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitCost = $get('unit_cost') ?? 0;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($unitCost * $state) - $discount);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('تكلفة الوحدة')
                                    ->numeric()
                                    ->required()
                                    ->step(0.0001)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($state * $quantity) - $discount);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('discount')
                                    ->label('خصم')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.0001)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitCost = $get('unit_cost') ?? 0;
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total', ($unitCost * $quantity) - $state);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('new_selling_price')
                                    ->label('سعر البيع الجديد')
                                    ->helperText('إذا تم تحديده، سيتم تحديث سعر المنتج تلقائياً')
                                    ->numeric()
                                    ->step(0.0001)
                                    ->nullable()
                                    ->disabled(fn ($record) => $record && $record->purchaseInvoice && $record->purchaseInvoice->isPosted()),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(7)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['product_id'] ? Product::find($state['product_id'])?->name : null)
                            ->disabled(fn ($record) => $record && $record->isPosted())
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                // Recalculate totals when items change
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');
                                $discount = $get('discount') ?? 0;
                                $set('subtotal', $subtotal);
                                $set('total', $subtotal - $discount);
                            }),
                    ]),

                Forms\Components\Section::make('الإجماليات')
                    ->schema([
                        Forms\Components\TextInput::make('subtotal')
                            ->label('المجموع الفرعي')
                            ->numeric()
                            ->default(0)
                            ->disabled()
                            ->dehydrated()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');
                                $discount = $get('discount') ?? 0;
                                $set('subtotal', $subtotal);
                                $set('total', $subtotal - $discount);
                            }),
                        Forms\Components\TextInput::make('discount')
                            ->label('الخصم الإجمالي')
                            ->numeric()
                            ->default(0)
                            ->step(0.0001)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = collect($items)->sum('total');
                                $set('subtotal', $subtotal);
                                $set('total', $subtotal - $state);
                            })
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\TextInput::make('total')
                            ->label('الإجمالي النهائي')
                            ->numeric()
                            ->required()
                            ->disabled()
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
                            ->step(0.01),
                        Forms\Components\TextInput::make('until')
                            ->label('إلى')
                            ->numeric()
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
                                    if ($item->new_selling_price !== null) {
                                        $stockService->updateProductPrice($item->product, $item->new_selling_price, $item->unit_type);
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
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseInvoice $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PurchaseInvoice $record) => $record->isDraft()),
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
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
