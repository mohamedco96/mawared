<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductCategoryResource\Pages;
use App\Models\ProductCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductCategoryResource extends Resource
{
    protected static ?string $model = ProductCategory::class;

    protected static ?string $cluster = \App\Filament\Clusters\InventorySettings::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'تصنيفات المنتجات';

    protected static ?string $modelLabel = 'تصنيف';

    protected static ?string $pluralModelLabel = 'تصنيفات المنتجات';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات التصنيف')
                    ->schema([
                        Forms\Components\Select::make('parent_id')
                            ->label('التصنيف الأب')
                            ->relationship('parent', 'name', function ($query, $get, $record) {
                                // Exclude current category from parent options
                                if ($record) {
                                    $query->where('id', '!=', $record->id);
                                }
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('- تصنيف رئيسي -'),

                        Forms\Components\TextInput::make('name')
                            ->label('اسم التصنيف')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                                if (! empty($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('name_en')
                            ->label('الاسم بالإنجليزية')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->label('الرابط المختصر')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('سيتم توليده تلقائياً من الاسم'),

                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('الصورة')
                    ->schema([
                        Forms\Components\FileUpload::make('image')
                            ->label('صورة التصنيف')
                            ->image()
                            ->directory('categories')
                            ->maxSize(2048)
                            ->imageEditor()
                            ->imageEditorAspectRatios(['16:9', '4:3', '1:1']),
                    ])->collapsible(),

                Forms\Components\Section::make('الإعدادات')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->inline(false),

                        Forms\Components\TextInput::make('display_order')
                            ->label('ترتيب العرض')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('يستخدم لترتيب التصنيفات (الأقل أولاً)'),
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

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('التصنيف الأب')
                    ->badge()
                    ->color('gray')
                    ->default('-')
                    ->searchable(),

                Tables\Columns\ImageColumn::make('image')
                    ->label('الصورة')
                    ->circular()
                    ->defaultImageUrl(url('/images/placeholder.svg')),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('عدد المنتجات')
                    ->counts('products')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('display_order')
                    ->label('الترتيب')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('display_order', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('التصنيف الأب')
                    ->relationship('parent', 'name')
                    ->preload()
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
                    ->before(function (Tables\Actions\DeleteAction $action, ProductCategory $record) {
                        if ($record->hasAssociatedRecords()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن حذف التصنيف')
                                ->body('لا يمكن حذف التصنيف لوجود منتجات أو تصنيفات فرعية مرتبطة به.')
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

                            $records->each(function (ProductCategory $record) use (&$skippedCount, &$deletedCount) {
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
            'index' => Pages\ListProductCategories::route('/'),
            'create' => Pages\CreateProductCategory::route('/create'),
            'edit' => Pages\EditProductCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['parent', 'children'])
            ->withCount('products');
    }
}
