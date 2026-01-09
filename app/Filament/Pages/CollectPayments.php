<?php

namespace App\Filament\Pages;

use App\Models\SalesInvoice;
use App\Models\Treasury;
use App\Services\TreasuryService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CollectPayments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.collect-payments';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'تحصيل الدفعات';

    protected static ?string $navigationGroup = 'المبيعات';

    protected static ?int $navigationSort = 3;

    public function getHeading(): string
    {
        return 'تحصيل الدفعات';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('رقم الفاتورة')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('partner.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('المدفوع')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('المبلغ المتبقي')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->color('danger')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الفاتورة')
                    ->date('Y-m-d')
                    ->sortable(),
                Tables\Columns\TextColumn::make('days_overdue')
                    ->label('أيام التأخير')
                    ->state(fn (SalesInvoice $record) =>
                        now()->diffInDays($record->created_at)
                    )
                    ->badge()
                    ->color(fn ($state) => match(true) {
                        $state > 30 => 'danger',
                        $state > 14 => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('partner_id')
                    ->label('العميل')
                    ->relationship('partner', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('overdue')
                    ->label('متأخرة')
                    ->query(fn ($query) =>
                        $query->where('created_at', '<', now()->subDays(30))
                    )
                    ->toggle(),
                Tables\Filters\Filter::make('has_installments')
                    ->label('بها أقساط')
                    ->query(fn ($query) =>
                        $query->where('has_installment_plan', true)
                    )
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\Action::make('quick_payment')
                    ->label('تسجيل دفعة')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('تسجيل دفعة سريعة')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->required()
                            ->default(fn (SalesInvoice $record) => $record->remaining_amount)
                            ->minValue(0.01)
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->rules([
                                'required',
                                'numeric',
                                fn (SalesInvoice $record): \Closure =>
                                    function ($attribute, $value, $fail) use ($record) {
                                        if ($value > $record->remaining_amount) {
                                            $fail('المبلغ أكبر من المتبقي (' . number_format($record->remaining_amount, 2) . ' ج.م)');
                                        }
                                    }
                            ])
                            ->helperText(fn (SalesInvoice $record) =>
                                'المبلغ المتبقي: ' . number_format($record->remaining_amount, 2) . ' ج.م'
                            ),
                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->options(Treasury::pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable(),
                        Forms\Components\Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(2),
                    ])
                    ->action(function (array $data, SalesInvoice $record) {
                        try {
                            $treasuryService = app(TreasuryService::class);
                            $treasuryService->recordInvoicePayment(
                                $record,
                                floatval($data['amount']),
                                0, // No settlement discount
                                $data['treasury_id'],
                                $data['notes'] ?? null
                            );

                            Notification::make()
                                ->success()
                                ->title('تم تسجيل الدفعة بنجاح')
                                ->body('تم إضافة ' . number_format($data['amount'], 2) . ' ج.م إلى الخزينة')
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في تسجيل الدفعة')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('view_invoice')
                    ->label('عرض الفاتورة')
                    ->icon('heroicon-o-eye')
                    ->url(fn (SalesInvoice $record) =>
                        \App\Filament\Resources\SalesInvoiceResource::getUrl('edit', ['record' => $record])
                    )
                    ->color('gray'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('bulk_payment')
                    ->label('تسجيل دفعات متعددة')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->modalHeading('تسجيل دفعة موزعة')
                    ->modalDescription('سيتم توزيع المبلغ على الفواتير المحددة')
                    ->modalWidth('md')
                    ->form([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('إجمالي المبلغ المدفوع')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'decimal'])
                            ->helperText('المبلغ الإجمالي الذي سيتم توزيعه على الفواتير'),
                        Forms\Components\Select::make('treasury_id')
                            ->label('الخزينة')
                            ->options(Treasury::pluck('name', 'id'))
                            ->required()
                            ->native(false)
                            ->searchable(),
                        Forms\Components\Toggle::make('distribute_equally')
                            ->label('توزيع بالتساوي')
                            ->default(false)
                            ->helperText('إذا تم التفعيل، سيتم توزيع المبلغ بالتساوي. وإلا سيتم دفع الفواتير بالترتيب حتى ينفذ المبلغ.'),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $treasuryService = app(TreasuryService::class);
                        $remainingAmount = floatval($data['total_amount']);
                        $successCount = 0;
                        $errors = [];

                        try {
                            if ($data['distribute_equally']) {
                                // Distribute equally
                                $perInvoice = $remainingAmount / $records->count();

                                foreach ($records as $invoice) {
                                    $payAmount = min($perInvoice, $invoice->remaining_amount);
                                    if ($payAmount > 0) {
                                        $treasuryService->recordInvoicePayment(
                                            $invoice,
                                            $payAmount,
                                            0,
                                            $data['treasury_id']
                                        );
                                        $successCount++;
                                    }
                                }
                            } else {
                                // Pay in order until money runs out
                                foreach ($records as $invoice) {
                                    if ($remainingAmount <= 0) break;

                                    $payAmount = min($remainingAmount, $invoice->remaining_amount);
                                    if ($payAmount > 0) {
                                        $treasuryService->recordInvoicePayment(
                                            $invoice,
                                            $payAmount,
                                            0,
                                            $data['treasury_id']
                                        );
                                        $remainingAmount -= $payAmount;
                                        $successCount++;
                                    }
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('تم تسجيل الدفعات بنجاح')
                                ->body("تم تسجيل {$successCount} دفعة")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('خطأ في تسجيل الدفعات')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->defaultSort('created_at', 'asc')
            ->poll('30s');
    }

    protected function getTableQuery(): Builder
    {
        return SalesInvoice::query()
            ->where('status', 'posted')
            ->where('remaining_amount', '>', 0)
            ->with(['partner'])
            ->orderBy('created_at', 'asc');
    }

    public static function canAccess(): bool
    {
        return auth()->user()->can('page_CollectPayments');
    }
}
