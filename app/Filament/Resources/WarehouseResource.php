<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'المخازن';

    protected static ?string $modelLabel = 'مخزن';

    protected static ?string $pluralModelLabel = 'المخازن';

    protected static ?string $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المخزن')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('رقم المخزن')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('name')
                            ->label('اسم المخزن')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('code')
                            ->label('رمز المخزن')
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->helperText('سيتم توليده تلقائياً إذا ترك فارغاً'),

                        Forms\Components\Textarea::make('address')
                            ->label('العنوان')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withSum('stockMovements', 'quantity'))
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم المخزن')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('الرمز')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('تم نسخ الرمز')
                    ->copyMessageDuration(1500)
                    ->default('—'),

                Tables\Columns\TextColumn::make('stock_movements_sum_quantity')
                    ->label('إجمالي الأصناف')
                    ->sortable()
                    ->badge()
                    ->color(function ($state) {
                        if ($state < 0) return 'danger';
                        if ($state == 0) return 'warning';
                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('address')
                    ->label('العنوان')
                    ->limit(40)
                    ->tooltip(fn (Warehouse $record): string => $record->address ?? '')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\Filter::make('has_code')
                    ->label('لديه رمز')
                    ->toggle()
                    ->query(fn ($query) => $query->whereNotNull('code')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function ($record, $action) {
                        // Use eager-loaded sum instead of re-querying
                        $totalStock = $record->stock_movements_sum_quantity ?? 0;

                        if ($totalStock > 0) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن حذف المخزن')
                                ->body("المخزن يحتوي على {$totalStock} وحدة. يجب إفراغ المخزن أولاً.")
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $blocked = [];
                            $deleted = [];

                            foreach ($records as $record) {
                                $totalStock = \App\Models\StockMovement::where('warehouse_id', $record->id)
                                    ->sum('quantity');

                                if ($totalStock > 0) {
                                    $blocked[] = $record->name . " ({$totalStock} وحدة)";
                                } else {
                                    $record->delete();
                                    $deleted[] = $record->name;
                                }
                            }

                            if (count($blocked) > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('بعض المخازن لم يتم حذفها')
                                    ->body('المخازن التالية تحتوي على مخزون: ' . implode(', ', $blocked))
                                    ->persistent()
                                    ->send();
                            }

                            if (count($deleted) > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('تم الحذف')
                                    ->body('تم حذف ' . count($deleted) . ' مخزن')
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
