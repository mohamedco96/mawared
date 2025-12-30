<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SalesInvoice;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('schedule_debt')
                ->label('جدولة المديونية')
                ->icon('heroicon-o-calendar')
                ->color('info')
                ->visible(fn (SalesInvoice $record) =>
                    $record->isPosted() &&
                    $record->current_remaining > 0 &&
                    !$record->installments()->exists()
                )
                ->form([
                    Forms\Components\TextInput::make('installment_months')
                        ->label('عدد الأقساط')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(120)
                        ->default(3)
                        ->required(),

                    Forms\Components\DatePicker::make('installment_start_date')
                        ->label('تاريخ أول قسط')
                        ->required()
                        ->default(now()->addMonth()->startOfMonth()),

                    Forms\Components\Textarea::make('installment_notes')
                        ->label('ملاحظات')
                        ->rows(2),
                ])
                ->action(function (SalesInvoice $record, array $data) {
                    try {
                        DB::transaction(function () use ($record, $data) {
                            // Update invoice with plan details
                            $record->update([
                                'has_installment_plan' => true,
                                'installment_months' => $data['installment_months'],
                                'installment_start_date' => $data['installment_start_date'],
                                'installment_notes' => $data['installment_notes'] ?? null,
                            ]);

                            // Generate installments
                            app(\App\Services\InstallmentService::class)
                                ->generateInstallmentSchedule($record);
                        });

                        Notification::make()
                            ->success()
                            ->title('تم جدولة المديونية')
                            ->body("تم إنشاء {$data['installment_months']} أقساط بنجاح")
                            ->send();

                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('خطأ في الجدولة')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('print')
                ->label('طباعة PDF')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('invoices.sales.print', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->isPosted())
                ->color('success'),
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }
}
