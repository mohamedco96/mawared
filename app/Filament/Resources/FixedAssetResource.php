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
                            ->live(onBlur: true)
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
                                fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    // Check treasury balance if funding method is cash
                                    if ($get('funding_method') === 'cash' && $get('treasury_id') && $value) {
                                        $treasuryService = app(TreasuryService::class);
                                        $currentBalance = (float) $treasuryService->getTreasuryBalance($get('treasury_id'));
                                        $purchaseAmount = (float) $value;
                                        
                                        if ($currentBalance < $purchaseAmount) {
                                            $fail("المبلغ المطلوب يتجاوز الرصيد المتاح في الخزينة. الرصيد الحالي: " . number_format($currentBalance, 2) . " ج.م");
                                        }
                                    }
                                },
                            ])
                            ->validationAttribute('قيمة الشراء'),
                        Forms\Components\DatePicker::make('purchase_date')
                            ->label('تاريخ الشراء')
                            ->required()
                            ->default(now())
                            ->displayFormat('Y-m-d')
                            ->maxDate(now())
                            ->helperText('تاريخ شراء الأصل'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('مصدر التمويل')
                    ->description('حدد كيف تم تمويل شراء هذا الأصل (محاسبة القيد المزدوج)')
                    ->schema([
                        Forms\Components\Select::make('funding_method')
                            ->label('طريقة التمويل')
                            ->options([
                                'payable' => 'شراء بالآجل (دائن / ذمم)',
                                'cash' => 'مدفوع فوراً من الخزينة',
                                'equity' => 'مساهمة رأسمالية من شريك',
                            ])
                            ->default('payable')
                            ->required()
                            ->live()
                            ->helperText('اختر مصدر التمويل لهذا الأصل')
                            ->columnSpanFull(),

                        // Cash Payment: Show Treasury Selector
                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->relationship('treasury', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => \App\Models\Treasury::first()?->id)
                            ->live(onBlur: true)
                            ->helperText('الخزينة التي سيتم الدفع منها')
                            ->visible(fn (Forms\Get $get) => $get('funding_method') === 'cash')
                            ->rules([
                                fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    // Only validate if funding method is cash and we have both treasury_id and purchase_amount
                                    if ($get('funding_method') === 'cash' && $value && $get('purchase_amount')) {
                                        $treasuryService = app(TreasuryService::class);
                                        $currentBalance = (float) $treasuryService->getTreasuryBalance($value);
                                        $purchaseAmount = (float) $get('purchase_amount');
                                        
                                        if ($currentBalance < $purchaseAmount) {
                                            $fail("الرصيد المتاح في الخزينة غير كافٍ. الرصيد الحالي: " . number_format($currentBalance, 2) . " ج.م، المبلغ المطلوب: " . number_format($purchaseAmount, 2) . " ج.م");
                                        }
                                    }
                                },
                            ])
                            ->validationAttribute('الخزينة'),

                        // Payable: Show Supplier Name or Selector
                        Forms\Components\TextInput::make('supplier_name')
                            ->label('اسم المورد')
                            ->maxLength(255)
                            ->helperText('اسم المورد الذي سيتم الشراء منه بالآجل')
                            ->visible(fn (Forms\Get $get) => $get('funding_method') === 'payable')
                            ->required(fn (Forms\Get $get) => $get('funding_method') === 'payable' && !$get('supplier_id')),

                        Forms\Components\Select::make('supplier_id')
                            ->label('أو اختر من الموردين المسجلين')
                            ->relationship('supplier', 'name', fn ($query) => $query->where('type', 'supplier'))
                            ->searchable()
                            ->preload()
                            ->helperText('اختر مورد موجود مسبقاً')
                            ->visible(fn (Forms\Get $get) => $get('funding_method') === 'payable'),

                        // Equity: Show Partner Selector
                        Forms\Components\Select::make('partner_id')
                            ->label('الشريك المساهم')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'shareholder'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get) => $get('funding_method') === 'equity')
                            ->helperText('اختر الشريك الذي قام بالمساهمة الرأسمالية')
                            ->visible(fn (Forms\Get $get) => $get('funding_method') === 'equity'),
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
                Tables\Columns\TextColumn::make('funding_method')
                    ->label('طريقة التمويل')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'cash' => 'نقدي',
                        'payable' => 'آجل',
                        'equity' => 'رأسمالي',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'cash' => 'success',
                        'payable' => 'warning',
                        'equity' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'active' => 'مسجل',
                        'draft' => 'مسودة',
                        default => $state,
                    })
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
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
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'draft' => 'مسودة',
                        'active' => 'مسجل',
                    ]),
                Tables\Filters\SelectFilter::make('funding_method')
                    ->label('طريقة التمويل')
                    ->options([
                        'cash' => 'نقدي',
                        'payable' => 'آجل',
                        'equity' => 'رأسمالي',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('post')
                    ->label('تسجيل الأصل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تسجيل الأصل الثابت')
                    ->modalDescription(fn (FixedAsset $record) => match($record->funding_method) {
                        'cash' => 'سيتم خصم المبلغ من الخزينة المحددة',
                        'payable' => 'سيتم إنشاء ذمة دائنة للمورد (الأصل مشترى بالآجل)',
                        'equity' => 'سيتم تسجيل مساهمة رأسمالية للشريك',
                        default => 'سيتم تسجيل الأصل الثابت',
                    })
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
