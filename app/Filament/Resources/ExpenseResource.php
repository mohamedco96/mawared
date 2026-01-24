<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-trending-down';

    protected static ?string $navigationLabel = 'المصروفات';

    protected static ?string $modelLabel = 'مصروف';

    protected static ?string $pluralModelLabel = 'المصروفات';

    protected static ?string $navigationGroup = 'المشتريات';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المصروف')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('البيان')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->live(onBlur: true)
                            ->rules([
                                fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    // Check treasury balance
                                    if ($get('treasury_id') && $value) {
                                        $treasuryService = app(\App\Services\TreasuryService::class);
                                        $currentBalance = (float) $treasuryService->getTreasuryBalance($get('treasury_id'));
                                        $expenseAmount = (float) $value;

                                        if ($currentBalance < $expenseAmount) {
                                            $fail('المبلغ المطلوب يتجاوز الرصيد المتاح في الخزينة. الرصيد الحالي: '.number_format($currentBalance, 2).' ج.م');
                                        }
                                    }
                                },
                            ]),
                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->relationship('treasury', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live(onBlur: true)
                            ->default(fn () => \App\Models\Treasury::where('type', 'cash')->first()?->id ?? \App\Models\Treasury::first()?->id)
                            ->rules([
                                fn (Forms\Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    // Check treasury balance
                                    if ($value && $get('amount')) {
                                        $treasuryService = app(\App\Services\TreasuryService::class);
                                        $currentBalance = (float) $treasuryService->getTreasuryBalance($value);
                                        $expenseAmount = (float) $get('amount');

                                        if ($currentBalance < $expenseAmount) {
                                            $fail('الرصيد المتاح في الخزينة غير كافٍ. الرصيد الحالي: '.number_format($currentBalance, 2).' ج.م، المبلغ المطلوب: '.number_format($expenseAmount, 2).' ج.م');
                                        }
                                    }
                                },
                            ])
                            ->validationAttribute('الخزينة'),
                        Forms\Components\DateTimePicker::make('expense_date')
                            ->label('تاريخ المصروف')
                            ->required()
                            ->default(now())
                            ->seconds(false)
                            ->timezone('Africa/Cairo')
                            ->displayFormat('Y-m-d H:i'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('تفاصيل المصروف')
                    ->description('معلومات إضافية عن المصروف والمستفيد')
                    ->schema([
                        Forms\Components\Select::make('expense_category_id')
                            ->label('تصنيف المصروف')
                            ->relationship('expenseCategory', 'name', fn ($query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('اسم التصنيف')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Select::make('type')
                                    ->label('نوع التصنيف')
                                    ->options(\App\Enums\ExpenseCategoryType::getSelectOptions())
                                    ->default('operational')
                                    ->required()
                                    ->native(false),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                return ExpenseCategory::create([
                                    'name' => $data['name'],
                                    'type' => $data['type'],
                                    'is_active' => true,
                                ])->id;
                            }),
                        Forms\Components\TextInput::make('beneficiary_name')
                            ->label('اسم المستفيد')
                            ->maxLength(255)
                            ->placeholder('الشخص أو الجهة المستلمة للمبلغ'),
                        Forms\Components\FileUpload::make('attachment')
                            ->label('إيصال / مرفق')
                            ->directory('expenses')
                            ->acceptedFileTypes(['image/*', 'application/pdf'])
                            ->maxSize(5120)
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('البيان')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expenseCategory.name')
                    ->label('التصنيف')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('beneficiary_name')
                    ->label('المستفيد')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->searchable()
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('treasury.name')
                    ->label('الخزينة')
                    ->sortable(),
                Tables\Columns\IconColumn::make('attachment')
                    ->label('مرفق')
                    ->boolean()
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('expense_date')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i')
                    ->timezone('Africa/Cairo')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('expense_category_id')
                    ->label('التصنيف')
                    ->relationship('expenseCategory', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('treasury_id')
                    ->label('الخزينة')
                    ->relationship('treasury', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('expense_date')
                    ->label('تاريخ المصروف')
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
                                fn ($query, $date) => $query->whereDate('expense_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn ($query, $date) => $query->whereDate('expense_date', '<=', $date),
                            );
                    }),
                Tables\Filters\Filter::make('amount')
                    ->label('المبلغ')
                    ->form([
                        Forms\Components\TextInput::make('from')
                            ->label('من')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01),
                        Forms\Components\TextInput::make('until')
                            ->label('إلى')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->step(0.01),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $amount) => $q->where('amount', '>=', $amount))
                            ->when($data['until'], fn ($q, $amount) => $q->where('amount', '<=', $amount));
                    }),
                Tables\Filters\TernaryFilter::make('has_attachment')
                    ->label('المرفقات')
                    ->placeholder('الكل')
                    ->trueLabel('مع مرفق')
                    ->falseLabel('بدون مرفق')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('attachment'),
                        false: fn ($query) => $query->whereNull('attachment'),
                    )
                    ->native(false),
            ], layout: FiltersLayout::Dropdown)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn ($record) => 'تفاصيل المصروف'),
            ])
            ->bulkActions([
                //
            ])
            ->defaultSort('expense_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
        ];
    }
}
