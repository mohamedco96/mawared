<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
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

    protected static ?string $navigationGroup = 'إدارة المخزون';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المخزن')
                    ->schema([
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

                Tables\Columns\TextColumn::make('total_items')
                    ->label('إجمالي الأصناف')
                    ->getStateUsing(function (Warehouse $record) {
                        return DB::table('stock_movements')
                            ->where('warehouse_id', $record->id)
                            ->sum('quantity') ?? 0;
                    })
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
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
