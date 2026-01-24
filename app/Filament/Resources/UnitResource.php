<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;

    protected static ?string $cluster = \App\Filament\Clusters\InventorySettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'وحدات القياس';

    protected static ?string $modelLabel = 'وحدة قياس';

    protected static ?string $pluralModelLabel = 'وحدات القياس';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('symbol')
                    ->label('الرمز')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('symbol')
                    ->label('الرمز')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver(),
                Tables\Actions\DeleteAction::make()
                    ->action(function ($record) {
                        try {
                            $record->delete();

                            Notification::make()
                                ->success()
                                ->title('تم الحذف')
                                ->body('تم حذف وحدة القياس بنجاح')
                                ->send();
                        } catch (\Illuminate\Database\QueryException $e) {
                            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                                Notification::make()
                                    ->danger()
                                    ->title('لا يمكن حذف وحدة القياس')
                                    ->body('هذه الوحدة مستخدمة في منتجات موجودة. يجب تغيير وحدة القياس للمنتجات أولاً.')
                                    ->persistent()
                                    ->send();
                            } else {
                                throw $e;
                            }
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $deleted = 0;
                            $blocked = [];

                            foreach ($records as $record) {
                                try {
                                    $record->delete();
                                    $deleted++;
                                } catch (\Illuminate\Database\QueryException $e) {
                                    if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'FOREIGN KEY')) {
                                        $blocked[] = $record->name;
                                    } else {
                                        throw $e;
                                    }
                                }
                            }

                            if ($deleted > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('تم الحذف')
                                    ->body("تم حذف {$deleted} وحدة قياس")
                                    ->send();
                            }

                            if (count($blocked) > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('بعض الوحدات مستخدمة')
                                    ->body('الوحدات التالية مستخدمة في منتجات: '.implode(', ', $blocked))
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
