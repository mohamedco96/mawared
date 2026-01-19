<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FixedAssetResource\Pages;
use App\Models\FixedAsset;
use App\Services\TreasuryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class FixedAssetResource extends Resource
{
    protected static ?string $model = FixedAsset::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'الأصول الثابتة (ممتلكات الشركة)';

    protected static ?string $modelLabel = 'أصل ثابت';

    protected static ?string $pluralModelLabel = 'الأصول الثابتة (ممتلكات الشركة)';

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

                Forms\Components\Section::make('مصدر التمويل (منين جبنا الفلوس)')
                    ->description('حدد كيف تم تمويل شراء هذا الأصل')
                    ->schema([
                        Forms\Components\Select::make('funding_method')
                            ->label('طريقة التمويل')
                            ->options([
                                'payable' => 'آجل (فلوس علينا للمورد)',
                                'cash' => 'نقدي من الخزينة (خروج فلوس)',
                                'equity' => 'مساهمة من شريك (فلوس من صاحب الشغل)',
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
                            ->label('الشريك المساهم (صاحب الشغل)')
                            ->relationship('partner', 'name', fn ($query) => $query->where('type', 'shareholder'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Forms\Get $get) => $get('funding_method') === 'equity')
                            ->helperText('اختر الشريك الذي قام بالمساهمة')
                            ->visible(fn (Forms\Get $get) => $get('funding_method') === 'equity'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('معلومات الاستهلاك')
                    ->description('إعدادات الاستهلاك السنوي للأصل الثابت')
                    ->schema([
                        Forms\Components\TextInput::make('useful_life_years')
                            ->label('العمر الإنتاجي (سنوات)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(5)
                            ->suffix('سنة')
                            ->helperText('عدد السنوات المتوقعة لاستخدام الأصل')
                            ->live(onBlur: true),

                        Forms\Components\TextInput::make('salvage_value')
                            ->label('قيمة الخردة (لو اتباع)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->suffix('ج.م')
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->helperText('القيمة المتبقية في نهاية العمر الإنتاجي')
                            ->live(onBlur: true),

                        Forms\Components\Select::make('depreciation_method')
                            ->label('طريقة الاستهلاك')
                            ->options(['straight_line' => 'القسط الثابت'])
                            ->default('straight_line')
                            ->disabled()
                            ->dehydrated(true)
                            ->helperText('طريقة حساب الاستهلاك (حالياً: القسط الثابت فقط)'),

                        Forms\Components\Toggle::make('is_contributed_asset')
                            ->label('مساهمة من شريك؟')
                            ->reactive()
                            ->default(fn (Get $get) => $get('funding_method') === 'equity')
                            ->helperText('هل هذا الأصل مساهمة من أحد الشركاء؟')
                            ->disabled(fn (Get $get) => $get('funding_method') === 'equity')
                            ->dehydrated(true),

                        Forms\Components\Select::make('contributing_partner_id')
                            ->label('الشريك المساهم')
                            ->relationship('contributingPartner', 'name', fn ($query) => $query->where('type', 'shareholder'))
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get) => $get('is_contributed_asset'))
                            ->visible(fn (Get $get) => $get('is_contributed_asset'))
                            ->default(fn (Get $get) => $get('partner_id'))
                            ->helperText('الشريك الذي ساهم بهذا الأصل'),

                        Forms\Components\Placeholder::make('monthly_depreciation_info')
                            ->label('الاستهلاك الشهري')
                            ->content(function (Get $get, $record) {
                                $amount = floatval($get('purchase_amount') ?? 0);
                                $salvage = floatval($get('salvage_value') ?? 0);
                                $years = intval($get('useful_life_years') ?? 5);

                                if ($amount > 0 && $years > 0) {
                                    $monthly = ($amount - $salvage) / ($years * 12);
                                    return number_format($monthly, 2) . ' ج.م شهرياً';
                                }

                                return '—';
                            })
                            ->helperText('يتم حسابه تلقائياً: (قيمة الشراء - قيمة الخردة) / (العمر الإنتاجي × 12)'),

                        Forms\Components\Placeholder::make('accumulated_depreciation_info')
                            ->label('الاستهلاك المتراكم')
                            ->content(fn ($record) => $record ? number_format($record->accumulated_depreciation, 2) . ' ج.م' : '0.00 ج.م')
                            ->visible(fn ($record) => $record !== null),

                        Forms\Components\Placeholder::make('book_value_info')
                            ->label('القيمة الدفترية (قيمته دلوقتي)')
                            ->content(function ($record, Get $get) {
                                if ($record && method_exists($record, 'getBookValue')) {
                                    return number_format($record->getBookValue(), 2) . ' ج.م';
                                }

                                $amount = floatval($get('purchase_amount') ?? 0);
                                return number_format($amount, 2) . ' ج.م';
                            })
                            ->helperText('قيمة الشراء - الاستهلاك المتراكم'),

                        Forms\Components\Placeholder::make('last_depreciation_info')
                            ->label('آخر استهلاك')
                            ->content(fn ($record) => $record && $record->last_depreciation_date
                                ? $record->last_depreciation_date->format('Y-m-d')
                                : 'لم يتم بعد')
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->columns(2)
                    ->collapsible(),
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
                Tables\Columns\TextColumn::make('book_value')
                    ->label('القيمة الدفترية (قيمته دلوقتي)')
                    ->getStateUsing(fn ($record) => method_exists($record, 'getBookValue') ? $record->getBookValue() : $record->purchase_amount)
                    ->numeric(decimalPlaces: 2)
                    ->badge()
                    ->color('success')
                    ->sortable(query: function ($query, string $direction) {
                        return $query->orderByRaw('(purchase_amount - accumulated_depreciation) ' . $direction);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('accumulated_depreciation')
                    ->label('الاستهلاك المتراكم')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_contributed_asset')
                    ->label('من شريك؟')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('contributingPartner.name')
                    ->label('الشريك المساهم (صاحب الشغل)')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
