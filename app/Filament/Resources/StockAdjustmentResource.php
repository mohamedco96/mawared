<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockAdjustmentResource\Pages;
use App\Models\StockAdjustment;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StockAdjustmentResource extends Resource
{
    protected static ?string $model = StockAdjustment::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    
    protected static ?string $navigationLabel = 'تسويات المخزون';
    
    protected static ?string $modelLabel = 'تسوية مخزون';
    
    protected static ?string $pluralModelLabel = 'تسويات المخزون';

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
                            ->relationship('warehouse', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('product_id')
                            ->label('المنتج')
                            ->relationship('product', 'name')
                            ->required()
                            ->searchable(['name', 'barcode', 'sku'])
                            ->preload()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\Select::make('type')
                            ->label('نوع التسوية')
                            ->options([
                                'damage' => 'تالف',
                                'opening' => 'افتتاحي',
                                'gift' => 'هدية',
                                'other' => 'أخرى',
                            ])
                            ->required()
                            ->native(false)
                            ->disabled(fn ($record) => $record && $record->isPosted()),
                        Forms\Components\TextInput::make('quantity')
                            ->label('الكمية')
                            ->helperText('قيمة موجبة للإضافة، قيمة سالبة للخصم (بالوحدة الأساسية)')
                            ->numeric()
                            ->required()
                            ->disabled(fn ($record) => $record && $record->isPosted()),
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
                        'damage' => 'تالف',
                        'opening' => 'افتتاحي',
                        'gift' => 'هدية',
                        'other' => 'أخرى',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'damage' => 'danger',
                        'opening' => 'success',
                        'gift' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('الكمية')
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
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
                        'damage' => 'تالف',
                        'opening' => 'افتتاحي',
                        'gift' => 'هدية',
                        'other' => 'أخرى',
                    ])
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تأكيد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (StockAdjustment $record) {
                        $stockService = app(StockService::class);

                        DB::transaction(function () use ($record, $stockService) {
                            // Post stock movement
                            $stockService->postStockAdjustment($record);

                            // Update adjustment status
                            $record->update(['status' => 'posted']);
                        });
                    })
                    ->visible(fn (StockAdjustment $record) => $record->isDraft()),
                Tables\Actions\EditAction::make()
                    ->visible(fn (StockAdjustment $record) => $record->isDraft()),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (StockAdjustment $record) => $record->isDraft()),
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
            'index' => Pages\ListStockAdjustments::route('/'),
            'create' => Pages\CreateStockAdjustment::route('/create'),
            'edit' => Pages\EditStockAdjustment::route('/{record}/edit'),
        ];
    }
}
