<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseReturnResource\Pages;
use App\Models\Product;
use App\Models\PurchaseReturn;
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

class PurchaseReturnResource extends Resource
{
    protected static ?string $model = PurchaseReturn::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'مرتجعات المشتريات';

    protected static ?string $modelLabel = 'مرتجع مشتريات';

    protected static ?string $pluralModelLabel = 'مرتجعات المشتريات';

    protected static ?string $navigationGroup = 'المشتريات';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المرتجع')
                    ->schema([
                        Forms\Components\TextInput::make('return_number')
                            ->label('رقم المرتجع')
                            ->default(fn () => 'RET-PURCHASE-'.now()->format('Ymd').'-'.Str::random(6))
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
                            ->reactive()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('partner_id')
                            ->label('المورد')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'supplier'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
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

                Forms\Components\Section::make('أصناف المرتجع')
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
                                                $set('unit_cost', $product->avg_cost);
                                                $set('quantity', 1);
                                                $set('discount', 0);
                                                $set('total', $product->avg_cost);
                                            }
                                        }
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseReturn && $record->purchaseReturn->isPosted()),
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
                                    ->disabled(fn ($record) => $record && $record->purchaseReturn && $record->purchaseReturn->isPosted()),
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
                                    ->disabled(fn ($record) => $record && $record->purchaseReturn && $record->purchaseReturn->isPosted()),
                                Forms\Components\TextInput::make('unit_cost')
                                    ->label('التكلفة')
                                    ->numeric()
                                    ->required()
                                    ->step(0.0001)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $quantity = $get('quantity') ?? 1;
                                        $discount = $get('discount') ?? 0;
                                        $set('total', ($state * $quantity) - $discount);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseReturn && $record->purchaseReturn->isPosted()),
                                Forms\Components\TextInput::make('discount')
                                    ->label('الخصم')
                                    ->numeric()
                                    ->default(0)
                                    ->step(0.0001)
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $unitCost = $get('unit_cost') ?? 0;
                                        $quantity = $get('quantity') ?? 1;
                                        $set('total', ($unitCost * $quantity) - $state);
                                    })
                                    ->disabled(fn ($record) => $record && $record->purchaseReturn && $record->purchaseReturn->isPosted()),
                                Forms\Components\TextInput::make('total')
                                    ->label('الإجمالي')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->columns(6)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['product_id'] ? Product::find($state['product_id'])?->name : null)
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                    ]),

                Forms\Components\Section::make('الإجماليات')
                    ->schema([
                        Forms\Components\Placeholder::make('calculated_subtotal')
                            ->label('المجموع الفرعي')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $subtotal += $item['total'] ?? 0;
                                }

                                return number_format($subtotal, 2).' ر.س';
                            }),
                        Forms\Components\TextInput::make('discount')
                            ->label('إجمالي الخصم')
                            ->numeric()
                            ->default(0)
                            ->step(0.0001)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $subtotal += $item['total'] ?? 0;
                                }
                                $set('subtotal', $subtotal);
                                $set('total', $subtotal - ($state ?? 0));
                            })
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Placeholder::make('calculated_total')
                            ->label('الإجمالي النهائي')
                            ->content(function (Get $get) {
                                $items = $get('items') ?? [];
                                $subtotal = 0;
                                foreach ($items as $item) {
                                    $subtotal += $item['total'] ?? 0;
                                }
                                $discount = $get('discount') ?? 0;
                                $total = $subtotal - $discount;

                                return number_format($total, 2).' ر.س';
                            }),
                        Forms\Components\Hidden::make('subtotal')
                            ->default(0)
                            ->dehydrated(),
                        Forms\Components\Hidden::make('total')
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('return_number')
                    ->label('رقم المرتجع')
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
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'posted' ? 'مؤكدة' : 'مسودة')
                    ->color(fn (string $state): string => $state === 'posted' ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('طريقة الدفع')
                    ->formatStateUsing(fn (string $state): string => $state === 'cash' ? 'نقدي' : 'آجل')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'cash' ? 'success' : 'info'),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->money('SAR')
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
            ])
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (PurchaseReturn $record) {
                        try {
                            $stockService = app(StockService::class);
                            $treasuryService = app(TreasuryService::class);

                            DB::transaction(function () use ($record, $stockService, $treasuryService) {
                                // Post stock movements
                                $stockService->postPurchaseReturn($record);

                                // Post treasury transactions
                                $treasuryService->postPurchaseReturn($record);

                                // Update return status
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
                    ->visible(fn (PurchaseReturn $record) => $record->isDraft()),
                Tables\Actions\EditAction::make()
                    ->visible(fn (PurchaseReturn $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (PurchaseReturn $record) => $record->isDraft()),
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
            'index' => Pages\ListPurchaseReturns::route('/'),
            'create' => Pages\CreatePurchaseReturn::route('/create'),
            'edit' => Pages\EditPurchaseReturn::route('/{record}/edit'),
        ];
    }
}
