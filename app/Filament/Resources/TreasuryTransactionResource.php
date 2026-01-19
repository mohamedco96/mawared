<?php

namespace App\Filament\Resources;

use App\Enums\TransactionType;
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

    protected static ?string $navigationLabel = 'حركة الخزنة (قبض وصرف)';

    protected static ?string $modelLabel = 'حركة مالية';

    protected static ?string $pluralModelLabel = 'حركة الخزنة (قبض وصرف)';

    protected static ?string $navigationGroup = 'الإدارة المالية';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات الحركة المالية')
                    ->schema([
                        // Virtual category field (not saved to DB)
                        Forms\Components\Select::make('transaction_category')
                            ->label('نوع العملية (قبض ولا صرف)')
                            ->options(TransactionType::getCategoryOptions())
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Reset dependent fields when category changes
                                $set('type', null);
                                $set('partner_id', null);
                                $set('employee_id', null);
                                $set('current_balance_display', null);
                                $set('employee_advance_balance_display', null);
                            })
                            // Auto-select category when editing existing record
                            ->afterStateHydrated(function (Set $set, Get $get, $state) {
                                if (!$state && $get('type')) {
                                    $typeEnum = TransactionType::from($get('type'));
                                    $set('transaction_category', $typeEnum->getCategory());
                                }
                            })
                            ->required()
                            ->dehydrated(false), // Don't save to database

                        Forms\Components\Select::make('type')
                            ->label('نوع الحركة')
                            ->options(function (Get $get) {
                                $category = $get('transaction_category');
                                if (!$category) {
                                    return [];
                                }

                                $types = TransactionType::forCategory($category);
                                return collect($types)
                                    ->mapWithKeys(fn(TransactionType $type) => [$type->value => $type->getLabel()])
                                    ->toArray();
                            })
                            ->required()
                            ->native(false)
                            ->live()
                            ->disabled(fn (Get $get) => !$get('transaction_category'))
                            ->afterStateUpdated(function (Set $set, $state) {
                                // Reset entity fields when type changes
                                $set('partner_id', null);
                                $set('employee_id', null);
                                $set('current_balance_display', null);
                                $set('employee_advance_balance_display', null);
                            }),

                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->relationship('treasury', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => \App\Models\Treasury::where('type', 'cash')->first()?->id ?? \App\Models\Treasury::first()?->id),

                        // Customer/Supplier/Partner field (for commercial and partner transactions)
                        Forms\Components\Select::make('partner_id')
                            ->label(function (Get $get) {
                                $type = $get('type');
                                if (in_array($type, ['collection', 'payment'])) {
                                    return 'العميل/المورد';
                                }
                                return 'الشريك';
                            })
                            ->relationship(
                                'partner',
                                'name',
                                function ($query, Get $get) {
                                    $type = $get('type');

                                    // For shareholder types
                                    if (in_array($type, ['capital_deposit', 'partner_drawing', 'partner_loan_receipt', 'partner_loan_repayment'])) {
                                        return $query->where('type', 'shareholder');
                                    }

                                    // For commercial types - exclude partners with zero balance
                                    if (in_array($type, ['collection', 'payment'])) {
                                        return $query->whereIn('type', ['customer', 'supplier'])
                                            ->where('current_balance', '!=', 0);
                                    }

                                    return $query->whereIn('type', ['customer', 'supplier']);
                                }
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
                            ->visible(fn (Get $get) => in_array($get('type'), [
                                'collection', 'payment', 'capital_deposit', 'partner_drawing',
                                'partner_loan_receipt', 'partner_loan_repayment'
                            ]))
                            ->required(fn (Get $get) => in_array($get('type'), [
                                'collection', 'payment', 'capital_deposit', 'partner_drawing',
                                'partner_loan_receipt', 'partner_loan_repayment'
                            ])),

                        Forms\Components\Placeholder::make('current_balance_display')
                            ->label('الرصيد الحالي (الموقف المالي)')
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

                        // Employee field (for HR transactions)
                        Forms\Components\Select::make('employee_id')
                            ->label('الموظف')
                            ->relationship('employee', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(fn (Get $get) => in_array($get('type'), ['employee_advance', 'salary_payment']))
                            ->visible(fn (Get $get) => in_array($get('type'), ['employee_advance', 'salary_payment']))
                            ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                if ($state) {
                                    $employee = \App\Models\User::find($state);
                                    if ($employee) {
                                        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'advance_balance')) {
                                            $set('employee_advance_balance_display', $employee->advance_balance);
                                        }
                                        if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'salary_amount')) {
                                            $set('employee_salary_display', $employee->salary_amount);
                                        }
                                    }
                                }
                            }),

                        Forms\Components\Placeholder::make('employee_advance_balance_display')
                            ->label('رصيد السلف (فلوس عليه)')
                            ->content(function (Get $get) {
                                $employeeId = $get('employee_id');
                                if ($employeeId) {
                                    $employee = \App\Models\User::find($employeeId);
                                    if ($employee && \Illuminate\Support\Facades\Schema::hasColumn('users', 'advance_balance')) {
                                        $balance = $employee->advance_balance;
                                        $color = $balance > 0 ? 'text-red-600' : 'text-gray-600';
                                        return new \Illuminate\Support\HtmlString(
                                            '<span class="' . $color . ' font-bold text-lg">' . number_format($balance, 2) . '</span>'
                                        );
                                    }
                                }
                                return '—';
                            })
                            ->visible(fn (Get $get) => $get('employee_id') !== null && $get('type') === 'employee_advance'),

                        Forms\Components\Placeholder::make('employee_salary_display')
                            ->label('الراتب المسجل')
                            ->content(function (Get $get) {
                                $employeeId = $get('employee_id');
                                if ($employeeId) {
                                    $employee = \App\Models\User::find($employeeId);
                                    if ($employee && \Illuminate\Support\Facades\Schema::hasColumn('users', 'salary_amount')) {
                                        $salary = $employee->salary_amount;
                                        if ($salary > 0) {
                                            return new \Illuminate\Support\HtmlString(
                                                '<span class="text-blue-600 font-bold text-lg">' . number_format($salary, 2) . ' ج.م</span>'
                                            );
                                        }
                                    }
                                }
                                return '—';
                            })
                            ->visible(fn (Get $get) => $get('employee_id') !== null && $get('type') === 'salary_payment'),

                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->required()
                            ->step(0.0001)
                            ->minValue(0.0001)
                            ->live(debounce: 500)
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
                            })
                            ->rules([
                                'required',
                                'numeric',
                                'gt:0',
                                fn (Get $get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                    if ($value === null || $value === '') {
                                        return;
                                    }

                                    $amount = floatval($value);

                                    // Validate positive amount
                                    if ($amount <= 0) {
                                        $fail('المبلغ يجب أن يكون أكبر من صفر.');
                                        return;
                                    }

                                    $type = $get('type');
                                    $treasuryId = $get('treasury_id');

                                    // For withdrawal types, check treasury balance
                                    $withdrawalTypes = ['payment', 'expense', 'partner_drawing', 'employee_advance', 'salary_payment', 'partner_loan_repayment'];
                                    if (in_array($type, $withdrawalTypes) && $treasuryId) {
                                        $treasury = \App\Models\Treasury::find($treasuryId);

                                        if ($treasury && $amount > $treasury->balance) {
                                            $fail('رصيد الخزينة غير كافٍ لإتمام هذه العملية. الرصيد الحالي: ' . number_format($treasury->balance, 2) . ' والمبلغ المطلوب: ' . number_format($amount, 2));
                                        }
                                    }
                                },
                            ])
                            ->validationAttribute('المبلغ'),

                        Forms\Components\TextInput::make('discount')
                            ->label('خصم')
                            ->numeric()
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
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
            ->modifyQueryUsing(fn ($query) => $query->with(['treasury', 'partner', 'employee', 'reference']))
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn (string $state): string => TransactionType::from($state)->getLabel())
                    ->badge()
                    ->color(fn (string $state): string => TransactionType::from($state)->getColor()),
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
                Tables\Columns\TextColumn::make('reference')
                    ->label('المرجع')
                    ->formatStateUsing(function (TreasuryTransaction $record) {
                        if (!$record->reference_type || !$record->reference_id) {
                            return '—';
                        }

                        // Handle financial_transaction separately as it's not a real model
                        if ($record->reference_type === 'financial_transaction') {
                            return 'معاملة مالية';
                        }

                        // Manually fetch the reference record based on type
                        try {
                            $reference = match($record->reference_type) {
                                'sales_invoice' => \App\Models\SalesInvoice::find($record->reference_id),
                                'purchase_invoice' => \App\Models\PurchaseInvoice::find($record->reference_id),
                                'sales_return' => \App\Models\SalesReturn::find($record->reference_id),
                                'purchase_return' => \App\Models\PurchaseReturn::find($record->reference_id),
                                default => null,
                            };

                            if (!$reference) {
                                return $record->reference_type;
                            }

                            return match($record->reference_type) {
                                'sales_invoice' => 'فاتورة بيع: ' . ($reference->invoice_number ?? '—'),
                                'purchase_invoice' => 'فاتورة شراء: ' . ($reference->invoice_number ?? '—'),
                                'sales_return' => 'مرتجع بيع: ' . ($reference->return_number ?? '—'),
                                'purchase_return' => 'مرتجع شراء: ' . ($reference->return_number ?? '—'),
                                default => $record->reference_type,
                            };
                        } catch (\Exception $e) {
                            return '—';
                        }
                    })
                    ->searchable(false)
                    ->sortable(false)
                    ->default('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('النوع')
                    ->options(function () {
                        return collect(TransactionType::cases())
                            ->mapWithKeys(fn(TransactionType $type) => [$type->value => $type->getLabel()])
                            ->toArray();
                    })
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                // Edit and delete actions removed - treasury transactions are immutable for audit trail
            ])
            ->bulkActions([
                // No bulk actions - treasury transactions should not be deleted
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTreasuryTransactions::route('/'),
            'create' => Pages\CreateTreasuryTransaction::route('/create'),
            // Edit page removed - treasury transactions are immutable for audit trail
        ];
    }
}
