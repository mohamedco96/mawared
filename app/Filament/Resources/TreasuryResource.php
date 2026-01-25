<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TreasuryResource\Pages;
use App\Models\Treasury;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class TreasuryResource extends Resource
{
    protected static ?string $model = Treasury::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'الخزائن';

    protected static ?string $modelLabel = 'خزينة';

    protected static ?string $pluralModelLabel = 'الخزائن';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الخزينة')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الخزينة')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('type')
                            ->label('النوع')
                            ->options([
                                'cash' => 'نقدية',
                                'bank' => 'بنك',
                            ])
                            ->required()
                            ->native(false)
                            ->default('cash'),

                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
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
                    ->label('اسم الخزينة')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'cash' => 'نقدية',
                        'bank' => 'بنك',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'cash' => 'success',
                        'bank' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('current_balance')
                    ->label('الرصيد الحالي')
                    ->getStateUsing(function (Treasury $record) {
                        return DB::table('treasury_transactions')
                            ->where('treasury_id', $record->id)
                            ->sum('amount') ?? 0;
                    })
                    ->numeric(decimalPlaces: 2)

                    ->sortable()
                    ->badge()
                    ->color(function ($state) {
                        if ($state < 0) {
                            return 'danger';
                        }
                        if ($state == 0) {
                            return 'gray';
                        }

                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(50)
                    ->tooltip(fn (Treasury $record): string => $record->description ?? '')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'cash' => 'نقدية',
                        'bank' => 'بنك',
                    ])
                    ->native(false),
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Treasury $record) {
                        if ($record->hasAssociatedRecords()) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('لا يمكن حذف الخزينة')
                                ->body('لا يمكن حذف الخزينة لوجود معاملات مالية أو مصروفات أو إيرادات أو أصول ثابتة مرتبطة بها.')
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

                            $records->each(function (Treasury $record) use (&$skippedCount, &$deletedCount) {
                                if ($record->hasAssociatedRecords()) {
                                    $skippedCount++;
                                } else {
                                    $record->delete();
                                    $deletedCount++;
                                }
                            });

                            if ($deletedCount > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('تم الحذف بنجاح')
                                    ->body("تم حذف {$deletedCount} خزينة")
                                    ->send();
                            }

                            if ($skippedCount > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('تم تخطي بعض السجلات')
                                    ->body("لم يتم حذف {$skippedCount} خزينة لوجود سجلات مرتبطة")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreasuries::route('/'),
            'create' => Pages\CreateTreasury::route('/create'),
            'edit' => Pages\EditTreasury::route('/{record}/edit'),
        ];
    }
}
