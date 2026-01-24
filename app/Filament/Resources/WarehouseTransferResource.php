<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseTransferResource\Pages;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\WarehouseTransfer;
use App\Services\StockService;
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

class WarehouseTransferResource extends Resource
{
    protected static ?string $model = WarehouseTransfer::class;

    protected static ?string $cluster = \App\Filament\Clusters\InventorySettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'نقل المخزون';

    protected static ?string $modelLabel = 'نقل مخزون';

    protected static ?string $pluralModelLabel = 'نقل المخزون';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات النقل')
                    ->schema([
                        Forms\Components\TextInput::make('transfer_number')
                            ->label('رقم النقل')
                            ->default(fn () => 'WT-'.now()->format('Ymd').'-'.Str::random(6))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->readOnly(fn ($context) => $context === 'edit'),
                        Forms\Components\Select::make('from_warehouse_id')
                            ->label('من المخزن')
                            ->relationship('fromWarehouse', 'name', fn ($query) => $query->where('is_active', true))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => Warehouse::where('is_active', true)->first()?->id ?? Warehouse::first()?->id)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                // Clear to_warehouse if same as from_warehouse
                                $toWarehouse = $get('to_warehouse_id');
                                if ($state === $toWarehouse) {
                                    $set('to_warehouse_id', null);
                                }
                            }),
                        Forms\Components\Select::make('to_warehouse_id')
                            ->label('إلى المخزن')
                            ->relationship('toWarehouse', 'name', fn ($query) => $query->where('is_active', true))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                // Clear to_warehouse if same as from_warehouse
                                $fromWarehouse = $get('from_warehouse_id');
                                if ($state === $fromWarehouse) {
                                    $set('to_warehouse_id', null);
                                }
                            })
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if ($value === $get('from_warehouse_id')) {
                                            $fail('يجب أن يكون المخزن الهدف مختلفاً عن المخزن المصدر');
                                        }
                                    };
                                },
                            ]),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('الأصناف')
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
                                    ->getSearchResultsUsing(function (?string $search): array {
                                        $query = Product::query();

                                        if (! empty($search)) {
                                            $query->where(function ($q) use ($search) {
                                                $q->where('name', 'like', "%{$search}%")
                                                    ->orWhere('sku', 'like', "%{$search}%")
                                                    ->orWhere('barcode', 'like', "%{$search}%");
                                            });
                                        } else {
                                            // Load latest products when no search
                                            $query->latest();
                                        }

                                        return $query->limit(10)->pluck('name', 'id')->toArray();
                                    })
                                    ->getOptionLabelUsing(function ($value): string {
                                        $product = Product::find($value);

                                        return $product ? $product->name : '';
                                    })
                                    ->loadingMessage('جاري التحميل...')
                                    ->searchPrompt('ابحث عن منتج بالاسم أو الباركود أو SKU')
                                    ->noSearchResultsMessage('لم يتم العثور على منتجات')
                                    ->searchingMessage('جاري البحث...')
                                    ->preload()
                                    ->reactive()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        if ($state) {
                                            // Get current stock from source warehouse
                                            $fromWarehouse = $get('../../from_warehouse_id');
                                            if ($fromWarehouse) {
                                                $stockService = app(StockService::class);
                                                $currentStock = $stockService->getCurrentStock($fromWarehouse, $state);
                                                $set('available_stock', $currentStock);
                                            }
                                        }
                                    }),
                                Forms\Components\Placeholder::make('available_stock')
                                    ->label('المخزون المتاح')
                                    ->content(function (Get $get) {
                                        $productId = $get('product_id');
                                        $fromWarehouse = $get('../../from_warehouse_id');
                                        if ($productId && $fromWarehouse) {
                                            $stockService = app(StockService::class);
                                            $stock = $stockService->getCurrentStock($fromWarehouse, $productId);

                                            return new \Illuminate\Support\HtmlString(
                                                '<span class="font-bold '.($stock > 0 ? 'text-green-600' : 'text-red-600').'">'.$stock.'</span>'
                                            );
                                        }

                                        return '—';
                                    })
                                    ->visible(fn (Get $get) => $get('product_id') !== null),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('الكمية (بالوحدة الأساسية)')
                                    ->numeric()
                                    ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                                    ->required()
                                    ->minValue(1)
                                    ->reactive()
                                    ->rules([
                                        function (Get $get) {
                                            return function (string $attribute, $value, \Closure $fail) use ($get) {
                                                $productId = $get('product_id');
                                                $fromWarehouse = $get('../../from_warehouse_id');
                                                if ($productId && $fromWarehouse && $value) {
                                                    $stockService = app(StockService::class);
                                                    $availableStock = $stockService->getCurrentStock($fromWarehouse, $productId);
                                                    if ($value > $availableStock) {
                                                        $fail("المخزون المتاح: {$availableStock}");
                                                    }
                                                }
                                            };
                                        },
                                    ]),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['product_id'] ? Product::find($state['product_id'])?->name : null),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull()
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['fromWarehouse', 'toWarehouse']))
            ->columns([
                Tables\Columns\TextColumn::make('transfer_number')
                    ->label('رقم النقل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fromWarehouse.name')
                    ->label('من المخزن')
                    ->sortable(),
                Tables\Columns\TextColumn::make('toWarehouse.name')
                    ->label('إلى المخزن')
                    ->sortable(),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('عدد الأصناف')
                    ->counts('items'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('from_warehouse_id')
                    ->label('من المخزن')
                    ->relationship('fromWarehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('to_warehouse_id')
                    ->label('إلى المخزن')
                    ->relationship('toWarehouse', 'name')
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
                Tables\Filters\SelectFilter::make('created_by')
                    ->label('المستخدم')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload(),
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تأكيد النقل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (WarehouseTransfer $record) {
                        // Validate transfer has items
                        if ($record->items()->count() === 0) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن تأكيد النقل')
                                ->body('النقل لا يحتوي على أي أصناف')
                                ->send();

                            return;
                        }

                        try {
                            $stockService = app(StockService::class);

                            DB::transaction(function () use ($record, $stockService) {
                                // Post warehouse transfer (creates dual stock movements)
                                $stockService->postWarehouseTransfer($record);
                            });

                            Notification::make()
                                ->success()
                                ->title('تم تأكيد النقل بنجاح')
                                ->body('تم تسجيل حركة المخزون')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في تأكيد النقل')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (WarehouseTransfer $record) => ! $record->stockMovements()->exists()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouseTransfers::route('/'),
            'create' => Pages\CreateWarehouseTransfer::route('/create'),
            'edit' => Pages\EditWarehouseTransfer::route('/{record}/edit'),
        ];
    }
}
