<?php

namespace App\Filament\Resources\SalesInvoiceResource\RelationManagers;

use App\Models\Installment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'خطة الأقساط';

    protected static ?string $modelLabel = 'قسط';

    protected static ?string $pluralModelLabel = 'أقساط';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('معلومات القسط')
                    ->schema([
                        Forms\Components\TextInput::make('installment_number')
                            ->label('رقم القسط')
                            ->required()
                            ->numeric()
                            ->disabled(),

                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->required()
                            ->numeric()
                            ->prefix('ج.م')
                            ->disabled(),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('تاريخ الاستحقاق')
                            ->required()
                            ->disabled()
                            ->native(false),

                        Forms\Components\TextInput::make('paid_amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            ->prefix('ج.م')
                            ->disabled()
                            ->default(0),

                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installment_number')
            ->defaultSort('installment_number', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('installment_number')
                    ->label('رقم القسط')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('EGP')
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->money('EGP')
                    ->sortable()
                    ->color(fn ($state, Installment $record) =>
                        bccomp((string) $state, (string) $record->amount, 4) === 0 ? 'success' : 'warning'
                    ),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('المتبقي')
                    ->money('EGP')
                    ->state(fn (Installment $record) => bcsub((string) $record->amount, (string) $record->paid_amount, 4))
                    ->color(fn ($state) => bccomp((string) $state, '0', 4) === 0 ? 'success' : 'danger')
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('due_date')
                    ->label('تاريخ الاستحقاق')
                    ->date('Y-m-d')
                    ->sortable()
                    ->color(fn (Installment $record) =>
                        $record->isOverdue() ? 'danger' : ($record->isPaid() ? 'success' : 'warning')
                    )
                    ->weight(FontWeight::SemiBold),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'قيد الانتظار',
                        'paid' => 'مدفوع',
                        'overdue' => 'متأخر',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'paid',
                        'danger' => 'overdue',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('تاريخ الدفع')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending' => 'قيد الانتظار',
                        'paid' => 'مدفوع',
                        'overdue' => 'متأخر',
                    ])
                    ->native(false),
            ])
            ->headerActions([
                // Installments are auto-generated, no create action
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('عرض'),
            ])
            ->bulkActions([
                // No bulk actions for installments
            ])
            ->emptyStateHeading('لا توجد أقساط')
            ->emptyStateDescription('لم يتم إنشاء خطة أقساط لهذه الفاتورة')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
