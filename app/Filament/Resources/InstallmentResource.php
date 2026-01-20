<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstallmentResource\Pages;
use App\Filament\Resources\InstallmentResource\RelationManagers;
use App\Models\Installment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\Enums\FontWeight;

class InstallmentResource extends Resource
{
    protected static ?string $model = Installment::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'المبيعات';

    protected static ?string $modelLabel = 'قسط';

    protected static ?string $pluralModelLabel = 'الأقساط';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات القسط')
                    ->schema([
                        Forms\Components\Select::make('sales_invoice_id')
                            ->label('الفاتورة')
                            ->relationship('salesInvoice', 'invoice_number')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(fn (?Installment $record) => $record !== null),

                        Forms\Components\TextInput::make('installment_number')
                            ->label('رقم القسط')
                            ->required()
                            ->numeric()
                            ->disabled(fn (?Installment $record) => $record !== null),

                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->required()
                            ->numeric()
                            
                            ->disabled(fn (?Installment $record) => $record !== null),

                        Forms\Components\DatePicker::make('due_date')
                            ->label('تاريخ الاستحقاق')
                            ->required()
                            ->native(false)
                            ->displayFormat('Y-m-d')
                            ->disabled(fn (?Installment $record) => $record !== null),
                    ])->columns(2),

                Forms\Components\Section::make('حالة الدفع')
                    ->schema([
                        Forms\Components\TextInput::make('paid_amount')
                            ->label('المبلغ المدفوع')
                            ->numeric()
                            
                            ->disabled()
                            ->default(0.0000),

                        Forms\Components\Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'pending' => 'قيد الانتظار',
                                'paid' => 'مدفوع',
                                'overdue' => 'متأخر',
                            ])
                            ->disabled()
                            ->required(),

                        Forms\Components\DateTimePicker::make('paid_at')
                            ->label('تاريخ الدفع')
                            ->disabled()
                            ->native(false),

                        Forms\Components\Select::make('paid_by')
                            ->label('تم الدفع بواسطة')
                            ->relationship('paidByUser', 'name')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('ملاحظات')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'asc')
            ->columns([
                Tables\Columns\TextColumn::make('salesInvoice.invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold)
                    ->url(fn (Installment $record) => route('filament.admin.resources.sales-invoices.edit', $record->sales_invoice_id))
                    ->color('primary'),

                Tables\Columns\TextColumn::make('salesInvoice.partner.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('installment_number')
                    ->label('رقم القسط')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->sortable()
                    ->color(fn ($state, Installment $record) =>
                        bccomp((string) $state, (string) $record->amount, 4) === 0 ? 'success' : 'warning'
                    ),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('المتبقي')
                    ->formatStateUsing(fn ($state) => number_format($state, 2))
                    ->state(fn (Installment $record) => bcsub((string) $record->amount, (string) $record->paid_amount, 4))
                    ->color(fn ($state) => bccomp((string) $state, '0', 4) === 0 ? 'success' : 'danger'),

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
                    ->sortable()
                    ->toggleable()
                    ->placeholder('لم يدفع بعد'),

                Tables\Columns\TextColumn::make('paidByUser.name')
                    ->label('تم الدفع بواسطة')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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

                Tables\Filters\Filter::make('overdue')
                    ->label('متأخرة فقط')
                    ->query(fn (Builder $query) => $query->where('status', 'overdue')
                        ->orWhere(function ($q) {
                            $q->where('status', 'pending')
                                ->where('due_date', '<', now());
                        })),

                Tables\Filters\Filter::make('due_soon')
                    ->label('مستحقة خلال 7 أيام')
                    ->query(fn (Builder $query) => $query
                        ->where('status', 'pending')
                        ->whereBetween('due_date', [now(), now()->addDays(7)])),

                Tables\Filters\Filter::make('due_date')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('من تاريخ')
                            ->native(false),
                        Forms\Components\DatePicker::make('until')
                            ->label('إلى تاريخ')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('due_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('من تاريخ: ' . \Carbon\Carbon::parse($data['from'])->format('Y-m-d'))
                                ->removeField('from');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = Tables\Filters\Indicator::make('إلى تاريخ: ' . \Carbon\Carbon::parse($data['until'])->format('Y-m-d'))
                                ->removeField('until');
                        }
                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('sales_invoice')
                    ->label('الفاتورة')
                    ->relationship('salesInvoice', 'invoice_number')
                    ->searchable()
                    ->preload()
                    ->native(false),

                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('العميل')
                    ->options(fn() => \App\Models\Partner::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->query(function ($query, $state) {
                        if ($state['value'] ?? null) {
                            $query->whereHas('salesInvoice', function ($q) use ($state) {
                                $q->where('partner_id', $state['value']);
                            });
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('عرض'),

                Tables\Actions\Action::make('view_invoice')
                    ->label('عرض الفاتورة')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->url(fn (Installment $record) => route('filament.admin.resources.sales-invoices.edit', $record->sales_invoice_id)),

                Tables\Actions\Action::make('view_payment')
                    ->label('عرض الدفعة')
                    ->icon('heroicon-o-credit-card')
                    ->color('success')
                    ->visible(fn (Installment $record) => $record->invoice_payment_id !== null)
                    ->modalContent(fn (Installment $record) => view('filament.modals.payment-details', [
                        'payment' => $record->invoicePayment
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق'),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Installment $record) {
                        if ($record->hasAssociatedRecords()) {
                            Notification::make()
                                ->danger()
                                ->title('لا يمكن الحذف')
                                ->body('لا يمكن حذف القسط لوجود مبالغ مدفوعة مرتبطة به.')
                                ->send();

                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Support\Collection $records) {
                            $skippedCount = 0;
                            $deletedCount = 0;

                            $records->each(function (Installment $record) use (&$skippedCount, &$deletedCount) {
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
                                    ->body("تم حذف {$deletedCount} قسط")
                                    ->send();
                            }

                            if ($skippedCount > 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('تم تخطي بعض السجلات')
                                    ->body("لم يتم حذف {$skippedCount} قسط لوجود مبالغ مدفوعة مرتبطة")
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('لا توجد أقساط')
            ->emptyStateDescription('لم يتم إنشاء أي أقساط بعد')
            ->emptyStateIcon('heroicon-o-calendar-days');
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
            'index' => Pages\ListInstallments::route('/'),
            'view' => Pages\ViewInstallment::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Installments are auto-generated
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'overdue')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
