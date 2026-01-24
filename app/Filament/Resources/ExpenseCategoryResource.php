<?php

namespace App\Filament\Resources;

use App\Enums\ExpenseCategoryType;
use App\Filament\Resources\ExpenseCategoryResource\Pages;
use App\Models\ExpenseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseCategoryResource extends Resource
{
    protected static ?string $model = ExpenseCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'تصنيفات المصروفات';

    protected static ?string $modelLabel = 'تصنيف مصروف';

    protected static ?string $pluralModelLabel = 'تصنيفات المصروفات';

    protected static ?string $navigationGroup = 'المشتريات';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات التصنيف')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم التصنيف')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('type')
                            ->label('نوع التصنيف')
                            ->options(ExpenseCategoryType::getSelectOptions())
                            ->default(ExpenseCategoryType::OPERATIONAL->value)
                            ->required()
                            ->native(false),

                        Forms\Components\Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (ExpenseCategoryType $state): string => $state->getLabel())
                    ->color(fn (ExpenseCategoryType $state): string => $state->getColor())
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->sortable(),

                Tables\Columns\TextColumn::make('expenses_count')
                    ->label('عدد المصروفات')
                    ->counts('expenses')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(ExpenseCategoryType::getSelectOptions())
                    ->native(false),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('نشط')
                    ->placeholder('الكل')
                    ->trueLabel('نشط فقط')
                    ->falseLabel('غير نشط فقط')
                    ->native(false),

                Tables\Filters\TrashedFilter::make()
                    ->label('المحذوفة')
                    ->native(false),
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('تعديل'),
                Tables\Actions\DeleteAction::make()
                    ->label('حذف')
                    ->before(function (Tables\Actions\DeleteAction $action, ExpenseCategory $record) {
                        if ($record->hasExpenses()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن حذف التصنيف')
                                ->body('لا يمكن حذف التصنيف لوجود مصروفات مرتبطة به.')
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('حذف المحدد')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $skippedCount = 0;
                            $deletedCount = 0;

                            $records->each(function (ExpenseCategory $record) use (&$skippedCount, &$deletedCount) {
                                if ($record->hasExpenses()) {
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
                                    ->body("تم حذف {$deletedCount} تصنيف")
                                    ->send();
                            }

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('تم تخطي بعض السجلات')
                                    ->body("لم يتم حذف {$skippedCount} تصنيف لوجود سجلات مرتبطة")
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenseCategories::route('/'),
            'create' => Pages\CreateExpenseCategory::route('/create'),
            'edit' => Pages\EditExpenseCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->withCount('expenses');
    }
}
