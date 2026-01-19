<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $cluster = \App\Filament\Clusters\InventorySettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'تسويات المخزون (جرد)';

    protected static ?string $modelLabel = 'تسوية مخزون';

    protected static ?string $pluralModelLabel = 'تسويات المخزون (جرد)';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات التسوية')
                    ->schema([
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
                            ->relationship('warehouse', 'name', fn ($query) => $query->where('is_active', true))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => Warehouse::where('is_active', true)->first()?->id ?? Warehouse::first()?->id)
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable(['name', 'barcode', 'sku'])
                            ->getSearchResultsUsing(function (?string $search): array {
                                $query = Product::query();

                                if (!empty($search)) {
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
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('type')
                            ->label('نوع التسوية')
                            ->options([
                                'addition' => 'إضافة',
                                'subtraction' => 'خصم',
                                'damage' => 'تالف',
                                'opening' => 'افتتاحي',
                                'gift' => 'هدية',
                                'other' => 'أخرى',
                            ])
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->isPosted())
                            ->live(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('الكمية')
                            ->helperText('أدخل الكمية كرقم صحيح موجب (بالوحدة الأساسية)')
                            ->integer()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric'])
                            ->required()
                            ->minValue(1)
                            ->disabled(fn ($record) => $record && $record->isPosted())
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, $state) {
                                // Convert negative to positive
                                if ($state !== null && intval($state) < 0) {
                                    $set('quantity', abs(intval($state)));
                                }
                            })
                            ->rules([
                                'required',
                                'integer',
                                'min:1',
                                fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    // Validate positive quantity
                                    if ($value !== null && intval($value) <= 0) {
                                        $fail('الكمية يجب أن تكون أكبر من صفر.');
                                        return;
                                    }

                                    $type = $get('type');
                                    $warehouseId = $get('warehouse_id');
                                    $productId = $get('product_id');

                                    // Ensure warehouse and product are selected
                                    if (!$warehouseId) {
                                        $fail('يجب اختيار المخزن أولاً.');
                                        return;
                                    }

                                    if (!$productId) {
                                        $fail('يجب اختيار المنتج أولاً.');
                                        return;
                                    }

                                    // For subtraction/damage/gift types, validate stock availability
                                    if (in_array($type, ['subtraction', 'damage', 'gift']) && $warehouseId && $productId && $value > 0) {
                                        $stockService = app(\App\Services\StockService::class);
                                        $currentStock = $stockService->getCurrentStock($warehouseId, $productId);

                                        if ($value > $currentStock) {
                                            $fail("الكمية المطلوبة ({$value}) أكبر من المخزون المتاح ({$currentStock}). لا يمكن خصم كمية تتجاوز المخزون الحالي.");
                                        }
                                    }
                                },
                            ])
                            ->validationAttribute('الكمية'),
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['warehouse', 'product']))
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('المخزن')
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'addition' => 'إضافة',
                        'subtraction' => 'خصم',
                        'damage' => 'تالف',
                        'opening' => 'افتتاحي',
                        'gift' => 'هدية',
                        'other' => 'أخرى',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'addition' => 'success',
                        'subtraction' => 'warning',
                        'damage' => 'danger',
                        'opening' => 'info',
                        'gift' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('الكمية')
                    ->sortable()
                    ->badge()
                    ->formatStateUsing(function ($state, $record) {
                        // Display with direction indicator based on type
                        $subtractionTypes = ['subtraction', 'damage', 'gift'];
                        $prefix = in_array($record->type, $subtractionTypes) ? '-' : '+';
                        return $prefix . abs($state);
                    })
                    ->color(function ($record) {
                        $subtractionTypes = ['subtraction', 'damage', 'gift'];
                        return in_array($record->type, $subtractionTypes) ? 'danger' : 'success';
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn (string $state): string => $state === 'draft' ? 'مسودة' : 'مؤكدة')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'draft' ? 'warning' : 'success'),
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
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'addition' => 'إضافة',
                        'subtraction' => 'خصم',
                        'damage' => 'تالف',
                        'opening' => 'افتتاحي',
                        'gift' => 'هدية',
                        'other' => 'أخرى',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('warehouse_id')
                    ->label('المخزن')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('المنتج')
                    ->relationship('product', 'name')
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
                Tables\Filters\Filter::make('quantity')
                    ->label('الكمية')
                    ->form([
                        Forms\Components\Select::make('type')
                            ->label('النوع')
                            ->options([
                                'positive' => 'إضافة',
                                'negative' => 'خصم',
                            ])
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        if (!isset($data['type'])) {
                            return $query;
                        }

                        return $query->when(
                            $data['type'] === 'positive',
                            fn ($q) => $q->where('quantity', '>', 0),
                            fn ($q) => $q->where('quantity', '<', 0)
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (StockAdjustment $record) {
                        try {
                            $stockService = app(StockService::class);

                            DB::transaction(function () use ($record, $stockService) {
                                // Post stock movement
                                $stockService->postStockAdjustment($record);

                                // Update adjustment status
                                $record->update(['status' => 'posted']);
                            });

                            Notification::make()
                                ->success()
                                ->title('تم تأكيد التسوية بنجاح')
                                ->body('تم تسجيل حركة المخزون')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في تأكيد التسوية')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (StockAdjustment $record) => $record->isDraft()),
                Tables\Actions\EditAction::make()
                    ->visible(fn (StockAdjustment $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, StockAdjustment $record) {
                        if ($record->hasAssociatedRecords()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن الحذف')
                                ->body('لا يمكن حذف حركة المخزون لأنها مؤكدة أو لها حركات مخزون مرتبطة.')
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

                            $records->each(function (StockAdjustment $record) use (&$skippedCount, &$deletedCount) {
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
                                    ->body("تم حذف {$deletedCount} حركة مخزون")
                                    ->send();
                            }

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('تم تخطي بعض السجلات')
                                    ->body("لم يتم حذف {$skippedCount} حركة مخزون لكونها مؤكدة أو لها حركات مرتبطة")
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
