<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FixedAssetResource\Pages;
use App\Models\FixedAsset;
use App\Services\TreasuryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class FixedAssetResource extends Resource
{
    protected static ?string $model = FixedAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'الأصول الثابتة';

    protected static ?string $modelLabel = 'أصل ثابت';

    protected static ?string $pluralModelLabel = 'الأصول الثابتة';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الأصل الثابت')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم الأصل')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('مثال: أثاث مكتبي، معدات، سيارة')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->placeholder('تفاصيل إضافية عن الأصل')
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('purchase_amount')
                            ->label('قيمة الشراء')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->required()
                            ->step(0.0001)
                            ->minValue(1)
                            
                            ->helperText('قيمة شراء الأصل الثابت')
                            ->rules([
                                'required',
                                'numeric',
                                'min:1',
                                fn (): \Closure => function (string $attribute, $value, \Closure $fail) {
                                    if ($value !== null && floatval($value) < 1) {
                                        $fail('قيمة الشراء يجب أن تكون 1 على الأقل.');
                                    }
                                },
                            ])
                            ->validationAttribute('قيمة الشراء'),
                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->relationship('treasury', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->helperText('الخزينة التي سيتم الدفع منها'),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('تاريخ الشراء')
                            ->required()
                            ->default(now())
                            ->displayFormat('Y-m-d')
                            ->maxDate(now())
                            ->helperText('تاريخ شراء الأصل'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الأصل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('purchase_amount')
                    ->label('قيمة الشراء')
                    ->numeric(decimalPlaces: 2)
                    
                    ->sortable(),
                Tables\Columns\TextColumn::make('treasury.name')
                    ->label('الخزينة')
                    ->sortable(),
                Tables\Columns\IconColumn::make('isPosted')
                    ->label('الحالة')
                    ->boolean()
                    ->getStateUsing(fn (FixedAsset $record) => $record->isPosted())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                Tables\Columns\TextColumn::make('purchase_date')
                    ->label('تاريخ الشراء')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('treasury_id')
                    ->label('الخزينة')
                    ->relationship('treasury', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('purchase_date')
                    ->label('تاريخ الشراء')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ'),
                        Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['from'],
                                fn ($query, $date) => $query->whereDate('purchase_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn ($query, $date) => $query->whereDate('purchase_date', '<=', $date),
                            );
                    }),
                Tables\Filters\TernaryFilter::make('posted')
                    ->label('مسجل في الخزينة')
                    ->queries(
                        true: fn ($query) => $query->whereHas('treasuryTransactions'),
                        false: fn ($query) => $query->whereDoesntHave('treasuryTransactions'),
                    ),
            ])
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تسجيل في الخزينة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تسجيل الأصل الثابت')
                    ->modalDescription('سيتم خصم المبلغ من الخزينة المحددة')
                    ->action(function (FixedAsset $record) {
                        $treasuryService = app(TreasuryService::class);

                        try {
                            DB::transaction(function () use ($record, $treasuryService) {
                                $treasuryService->postFixedAssetPurchase($record);
                            });

                            Notification::make()
                                ->success()
                                ->title('تم التسجيل بنجاح')
                                ->body('تم تسجيل الأصل الثابت وخصم المبلغ من الخزينة')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في التسجيل')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (FixedAsset $record) => !$record->isPosted()),
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (FixedAsset $record) => !$record->isPosted())
                    ->before(function (FixedAsset $record, Tables\Actions\DeleteAction $action) {
                        if ($record->isPosted()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن الحذف')
                                ->body('لا يمكن حذف أصل ثابت مسجل في الخزينة')
                                ->send();
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $postedRecords = $records->filter(fn ($r) => $r->isPosted());
                            $draftRecords = $records->filter(fn ($r) => !$r->isPosted());

                            if ($postedRecords->count() > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('تحذير')
                                    ->body("تم تجاهل {$postedRecords->count()} أصل مسجل. يمكن حذف الأصول غير المسجلة فقط.")
                                    ->send();
                            }

                            $draftRecords->each->delete();
                        }),
                ]),
            ])
            ->defaultSort('purchase_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFixedAssets::route('/'),
            'create' => Pages\CreateFixedAsset::route('/create'),
            'edit' => Pages\EditFixedAsset::route('/{record}/edit'),
        ];
    }
}
