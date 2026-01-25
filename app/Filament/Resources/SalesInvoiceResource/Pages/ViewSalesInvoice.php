<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SalesInvoice;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

            // A4 Print Action
            Actions\Action::make('print_a4')
                ->label('طباعة (A4)')
                ->icon('heroicon-o-document-text')
                ->url(fn () => route('invoices.sales.print', [
                    'invoice' => $this->record,
                    'format' => 'a4'
                ]))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->isPosted())
                ->color('primary'),

            // Thermal Print Action
            Actions\Action::make('print_thermal')
                ->label('طباعة (حراري)')
                ->icon('heroicon-o-receipt-percent')
                ->url(fn () => route('invoices.sales.print', [
                    'invoice' => $this->record,
                    'format' => 'thermal'
                ]))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->isPosted())
                ->color('success'),
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->record->hasAssociatedRecords()),
            Actions\Action::make('create_return')
                ->label('إنشاء مرتجع')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (SalesInvoice $record) => $record->isPosted() && !$record->isPostedFullyReturned())
                ->requiresConfirmation()
                ->modalHeading('إنشاء مرتجع مبيعات')
                ->modalDescription(fn (SalesInvoice $record) => $record->returns()->where('status', 'draft')->exists()
                    ? '⚠️ تنبيه: توجد مسودات مرتجعات سابقة لهذه الفاتورة. هل تريد إنشاء مسودة جديدة على أي حال؟ (سيتم تحميل الكميات المتبقية فقط)'
                    : 'سيتم إنشاء مسودة مرتجع بناءً على هذه الفاتورة. هل أنت متأكد؟')
                ->action(function (SalesInvoice $record) {
                    return DB::transaction(function () use ($record) {
                        $return = SalesReturn::create([
                            'return_number' => 'RET-SALE-' . now()->format('Ymd') . '-' . Str::random(6),
                            'warehouse_id' => $record->warehouse_id,
                            'partner_id' => $record->partner_id,
                            'sales_invoice_id' => $record->id,
                            'status' => 'draft',
                            'payment_method' => $record->payment_method,
                            'subtotal' => $record->subtotal,
                            'discount' => $record->discount,
                            'total' => $record->total,
                            'notes' => 'مرتجع من الفاتورة: ' . $record->invoice_number,
                            'created_by' => auth()->id(),
                        ]);

                        foreach ($record->items as $item) {
                            $availableQty = $record->getAvailableReturnQuantity($item->product_id, $item->unit_type);
                            if ($availableQty > 0) {
                                SalesReturnItem::create([
                                    'sales_return_id' => $return->id,
                                    'product_id' => $item->product_id,
                                    'unit_type' => $item->unit_type,
                                    'quantity' => $availableQty,
                                    'unit_price' => $item->unit_price,
                                    'discount' => $item->discount,
                                    'total' => $item->unit_price * $availableQty,
                                ]);
                            }
                        }

                        return redirect()->route('filament.admin.resources.sales-returns.edit', ['record' => $return->id]);
                    });
                }),
        ];
    }
}
