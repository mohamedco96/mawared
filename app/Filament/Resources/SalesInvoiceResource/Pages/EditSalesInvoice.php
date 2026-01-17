<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_return')
                ->label('إنشاء مرتجع')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->isPosted())
                ->action(function () {
                    // Create a new sales return based on this invoice
                    $invoice = $this->record;

                    $return = SalesReturn::create([
                        'return_number' => 'RET-SALE-' . now()->format('Ymd') . '-' . Str::random(6),
                        'warehouse_id' => $invoice->warehouse_id,
                        'partner_id' => $invoice->partner_id,
                        'sales_invoice_id' => $invoice->id,
                        'status' => 'draft',
                        'payment_method' => $invoice->payment_method,
                        'subtotal' => $invoice->subtotal,
                        'discount' => $invoice->discount,
                        'total' => $invoice->total,
                        'notes' => 'مرتجع من الفاتورة: ' . $invoice->invoice_number,
                        'created_by' => auth()->id(),
                    ]);

                    // Replicate items
                    foreach ($invoice->items as $item) {
                        SalesReturnItem::create([
                            'sales_return_id' => $return->id,
                            'product_id' => $item->product_id,
                            'unit_type' => $item->unit_type,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'discount' => $item->discount,
                            'total' => $item->total,
                        ]);
                    }

                    // Redirect to edit the return
                    return redirect()->route('filament.admin.resources.sales-returns.edit', ['record' => $return->id]);
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Only allow editing if draft
        if ($this->record->isPosted()) {
            return $data;
        }

        // Calculate subtotal and total from items
        $subtotal = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $subtotal += $item['total'] ?? 0;
            }
        }

        $discount = $data['discount'] ?? 0;
        $total = $subtotal - $discount;

        $data['subtotal'] = $subtotal;
        $data['total'] = $total;

        // Calculate commission amount
        if (!empty($data['sales_person_id']) && !empty($data['commission_rate'])) {
            $commissionRate = floatval($data['commission_rate']) / 100;
            $data['commission_amount'] = $total * $commissionRate;
        } else {
            $data['commission_amount'] = 0;
        }

        // FIX: Ensure paid_amount is set correctly for cash invoices
        // For cash payment: paid_amount should equal total
        // For credit payment: paid_amount comes from user input (or default 0)
        if (($data['payment_method'] ?? null) === 'cash') {
            $data['paid_amount'] = $total;
            $data['remaining_amount'] = 0;
        } elseif (($data['payment_method'] ?? null) === 'credit') {
            $data['paid_amount'] = floatval($data['paid_amount'] ?? 0);
            $data['remaining_amount'] = $total - $data['paid_amount'];
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        // Check if status was changed from draft to posted
        if ($record->wasChanged('status') && $record->status === 'posted') {
            try {
                // Wrap everything in a single transaction to ensure atomicity
                DB::transaction(function () use ($record) {
                    // Temporarily set to draft for service validation
                    $record->status = 'draft';

                    // 1. Post stock movements (deduct stock)
                    app(\App\Services\StockService::class)->postSalesInvoice($record);

                    // 2. Post treasury transactions (add to treasury)
                    app(\App\Services\TreasuryService::class)->postSalesInvoice($record);

                    // 3. Update status back to posted (using saveQuietly to bypass model events)
                    $record->status = 'posted';
                    $record->saveQuietly();

                    // 4. Generate installments if plan is enabled
                    if ($record->has_installment_plan) {
                        app(\App\Services\InstallmentService::class)
                            ->generateInstallmentSchedule($record);
                    }
                });

                // Show success notification (outside transaction)
                \Filament\Notifications\Notification::make()
                    ->title($record->has_installment_plan
                        ? "تم ترحيل الفاتورة وإنشاء {$record->installment_months} أقساط"
                        : 'تم ترحيل الفاتورة وتحديث المخزون والخزينة')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                // Transaction failed, all changes rolled back automatically
                usleep(100000); // 100ms

                // Reload the record to get the current state from database
                $record->refresh();

                // IMPORTANT: Set invoice status back to 'draft' since posting failed
                if ($record->status === 'posted') {
                    DB::transaction(function () use ($record) {
                        $record->status = 'draft';
                        $record->saveQuietly();
                    });
                }

                // Show error notification
                \Filament\Notifications\Notification::make()
                    ->title('خطأ في ترحيل الفاتورة')
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->send();

                // Re-throw to prevent the page from showing success
                throw $e;
            }
        }
    }
}
