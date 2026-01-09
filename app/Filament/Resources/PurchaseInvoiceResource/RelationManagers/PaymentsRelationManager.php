<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\RelationManagers;

use App\Models\Treasury;
use App\Services\TreasuryService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'الدفعات / المدفوعات';

    protected static ?string $modelLabel = 'دفعة';

    protected static ?string $pluralModelLabel = 'الدفعات';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Grid::make(2)
                    ->schema([
                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue(fn () => $this->getOwnerRecord()->current_remaining ?? $this->getOwnerRecord()->remaining_amount)
                            ->default(fn () => $this->getOwnerRecord()->current_remaining ?? $this->getOwnerRecord()->remaining_amount)
                            
                            ->step(0.01),

                        Forms\Components\DatePicker::make('payment_date')
                            ->label('تاريخ الدفع')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),

                        Forms\Components\TextInput::make('discount')
                            ->label('خصم التسوية')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            
                            ->step(0.01)
                            ->helperText('خصم مسموح به عند السداد'),

                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->options(Treasury::pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->default(fn () => Treasury::first()?->id),
                    ]),

                Forms\Components\Textarea::make('notes')
                    ->label('ملاحظات')
                    ->maxLength(500)
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                Tables\Columns\TextColumn::make('payment_date')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->sortable(),

                Tables\Columns\TextColumn::make('discount')
                    ->label('الخصم')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->default(0),

                Tables\Columns\TextColumn::make('treasuryTransaction.treasury.name')
                    ->label('الخزينة')
                    ->default('-'),

                Tables\Columns\TextColumn::make('notes')
                    ->label('الملاحظات')
                    ->limit(50)
                    ->default('-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('المستخدم')
                    ->default('-'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة دفعة / سداد')
                    ->icon('heroicon-o-plus-circle')
                    ->modalHeading('تسجيل دفعة جديدة')
                    ->modalWidth('lg')
                    // Override the default create behavior to use TreasuryService
                    ->using(function (array $data, RelationManager $livewire) {
                        $invoice = $livewire->getOwnerRecord();
                        $treasuryService = app(TreasuryService::class);

                        // Call the service to handle payment creation
                        return $treasuryService->recordInvoicePayment(
                            $invoice,
                            floatval($data['amount']),
                            floatval($data['discount'] ?? 0),
                            $data['treasury_id'],
                            $data['notes'] ?? null
                        );
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('تم تسجيل الدفعة بنجاح')
                            ->body('تم إضافة الدفعة وتحديث رصيد المورد والخزينة')
                    )
                    ->visible(fn (RelationManager $livewire) =>
                        $livewire->getOwnerRecord()->isPosted() &&
                        ($livewire->getOwnerRecord()->current_remaining ?? $livewire->getOwnerRecord()->remaining_amount) > 0
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('عرض'),
            ])
            ->bulkActions([
                // Payments should not be bulk deleted for audit trail
            ])
            ->emptyStateHeading('لا توجد دفعات مسجلة')
            ->emptyStateDescription('سيتم عرض الدفعات المسجلة على هذه الفاتورة هنا')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }
}
