<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TreasuryTransactionResource\Pages;
use App\Models\Partner;
use App\Models\TreasuryTransaction;
use App\Services\TreasuryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class TreasuryTransactionResource extends Resource
{
    protected static ?string $model = TreasuryTransaction::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'المعاملات المالية';

    protected static ?string $modelLabel = 'معاملة مالية';

    protected static ?string $pluralModelLabel = 'المعاملات المالية';

    protected static ?string $navigationGroup = 'المالية والشركاء';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات المعاملة')
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('نوع المعاملة')
                            ->options([
                                'collection' => 'تحصيل',
                                'payment' => 'دفع',
                                'capital_deposit' => 'إيداع رأس المال',
                                'partner_drawing' => 'سحب شريك',
                                'employee_advance' => 'سلفة موظف',
                            ])
                            ->required()
                            ->native(false)
                            ->live(),
                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->relationship('treasury', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('partner_id')
                            ->label('الشريك')
                            ->relationship(
                                'partner',
                                'name',
                                fn ($query, Get $get) => $get('type') === 'capital_deposit' || $get('type') === 'partner_drawing'
                                    ? $query->where('type', 'shareholder')
                                    : $query->whereIn('type', ['customer', 'supplier'])
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($state) {
                                    $partner = Partner::find($state);
                                    if ($partner) {
                                        $set('current_balance_display', $partner->current_balance);
                                    }
                                }
                            })
                            ->visible(fn (Get $get) => in_array($get('type'), ['collection', 'payment', 'capital_deposit', 'partner_drawing'])),
                        Forms\Components\Placeholder::make('current_balance_display')
                            ->label('الرصيد الحالي')
                            ->content(function (Get $get) {
                                $partnerId = $get('partner_id');
                                if ($partnerId) {
                                    $partner = Partner::find($partnerId);
                                    if ($partner) {
                                        $balance = $partner->current_balance;
                                        $color = $balance < 0 ? 'text-red-600' : ($balance > 0 ? 'text-green-600' : 'text-gray-600');
                                        return new \Illuminate\Support\HtmlString(
                                            '<span class="' . $color . ' font-bold text-lg">' . number_format($balance, 2) . '</span>'
                                        );
                                    }
                                }
                                return '—';
                            })
                            ->visible(fn (Get $get) => $get('partner_id') !== null),
                        Forms\Components\Select::make('employee_id')
                            ->label('الموظف')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(fn (Get $get) => $get('type') === 'employee_advance')
                            ->visible(fn (Get $get) => $get('type') === 'employee_advance')
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $employee = \App\Models\User::find($state);
                                    if ($employee && \Illuminate\Support\Facades\Schema::hasColumn('users', 'advance_balance')) {
                                        $set('employee_advance_balance_display', $employee->advance_balance);
                                    }
                                }
                            }),
                        Forms\Components\Placeholder::make('employee_advance_balance_display')
                            ->label('رصيد السلف الحالي')
                            ->content(function (Get $get) {
                                $employeeId = $get('employee_id');
                                if ($employeeId) {
                                    $employee = \App\Models\User::find($employeeId);
                                    if ($employee && \Illuminate\Support\Facades\Schema::hasColumn('users', 'advance_balance')) {
                                        $balance = $employee->advance_balance;
                                        $color = $balance > 0 ? 'text-red-600' : 'text-gray-600';
                                        return new \Illuminate\Support\HtmlString(
                                            '<span class="' . $color . ' font-bold text-lg">' . number_format($balance, 2) . ' ج.م</span>'
                                        );
                                    }
                                }
                                return '—';
                            })
                            ->visible(fn (Get $get) => $get('employee_id') !== null && $get('type') === 'employee_advance'),
                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->required()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                // Calculate final amount with discount
                                $discount = $get('discount') ?? 0;
                                $type = $get('type');
                                if ($type === 'collection' && $discount > 0) {
                                    $finalAmount = $state - $discount;
                                    $set('final_amount', max(0, $finalAmount));
                                } else {
                                    $set('final_amount', $state);
                                }
                            }),
                        Forms\Components\TextInput::make('discount')
                            ->label('خصم')
                            ->numeric()
                            ->default(0)
                            ->step(0.01)
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                $amount = $get('amount') ?? 0;
                                $finalAmount = $amount - $state;
                                $set('final_amount', max(0, $finalAmount));
                            })
                            ->visible(fn (Get $get) => in_array($get('type'), ['collection', 'payment'])),
                        Forms\Components\Hidden::make('final_amount'),
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->required()
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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'collection' => 'تحصيل',
                        'payment' => 'دفع',
                        'income' => 'إيراد',
                        'expense' => 'مصروف',
                        'capital_deposit' => 'إيداع رأس المال',
                        'partner_drawing' => 'سحب شريك',
                        'employee_advance' => 'سلفة موظف',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'collection', 'income', 'capital_deposit' => 'success',
                        'payment', 'expense', 'partner_drawing', 'employee_advance' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('treasury.name')
                    ->label('الخزينة')
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('الشريك')
                    ->searchable()
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('employee.name')
                    ->label('الموظف')
                    ->searchable()
                    ->sortable()
                    ->default('—'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->limit(50)
                    ->tooltip(fn (TreasuryTransaction $record): string => $record->description),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options([
                        'collection' => 'تحصيل',
                        'payment' => 'دفع',
                        'income' => 'إيراد',
                        'expense' => 'مصروف',
                        'capital_deposit' => 'إيداع رأس المال',
                        'partner_drawing' => 'سحب شريك',
                        'employee_advance' => 'سلفة موظف',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('treasury_id')
                    ->label('الخزينة')
                    ->relationship('treasury', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('الشريك')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('الموظف')
                    ->relationship('employee', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ'),
                        Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
                Tables\Filters\Filter::make('amount')
                    ->label('المبلغ')
                    ->form([
                        Forms\Components\TextInput::make('from')
                            ->label('من')
                            ->numeric()
                            ->step(0.01),
                        Forms\Components\TextInput::make('until')
                            ->label('إلى')
                            ->numeric()
                            ->step(0.01),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $amount) => $q->where('amount', '>=', $amount))
                            ->when($data['until'], fn ($q, $amount) => $q->where('amount', '<=', $amount));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListTreasuryTransactions::route('/'),
            'create' => Pages\CreateTreasuryTransaction::route('/create'),
            'edit' => Pages\EditTreasuryTransaction::route('/{record}/edit'),
        ];
    }
}
