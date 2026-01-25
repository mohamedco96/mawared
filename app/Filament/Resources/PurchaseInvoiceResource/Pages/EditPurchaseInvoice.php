<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function beforeSave(): void
    {
        if ($this->getRecord()->isPosted()) {
            Notification::make()
                ->warning()
                ->title('لا يمكن تعديل فاتورة مؤكدة')
                ->body('نأسف، لا يمكن تعديل الفاتورة بعد الترحيل')
                ->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_return')
                ->label('إنشاء مرتجع')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->isPosted() && !$this->record->isPostedFullyReturned())
                ->requiresConfirmation()
                ->modalHeading('إنشاء مرتجع مشتريات')
                ->modalDescription(fn () => $this->record->returns()->where('status', 'draft')->exists()
                    ? '⚠️ تنبيه: توجد مسودات مرتجعات سابقة لهذه الفاتورة. هل تريد إنشاء مسودة جديدة على أي حال؟ (سيتم تحميل الكميات المتبقية فقط)'
                    : 'سيتم إنشاء مسودة مرتجع بناءً على هذه الفاتورة. هل أنت متأكد؟')
                ->action(function () {
                    return DB::transaction(function () {
                        $invoice = $this->record;

                        $return = PurchaseReturn::create([
                            'return_number' => 'RET-PURCHASE-' . now()->format('Ymd') . '-' . Str::random(6),
                            'warehouse_id' => $invoice->warehouse_id,
                            'partner_id' => $invoice->partner_id,
                            'purchase_invoice_id' => $invoice->id,
                            'status' => 'draft',
                            'payment_method' => $invoice->payment_method,
                            'subtotal' => $invoice->subtotal,
                            'discount' => $invoice->discount,
                            'total' => $invoice->total,
                            'notes' => 'مرتجع من الفاتورة: ' . $invoice->invoice_number,
                            'created_by' => auth()->id(),
                        ]);

                        foreach ($invoice->items as $item) {
                            $availableQty = $invoice->getAvailableReturnQuantity($item->product_id, $item->unit_type);
                            if ($availableQty > 0) {
                                PurchaseReturnItem::create([
                                    'purchase_return_id' => $return->id,
                                    'product_id' => $item->product_id,
                                    'unit_type' => $item->unit_type,
                                    'quantity' => $availableQty,
                                    'unit_cost' => $item->unit_cost,
                                    'discount' => $item->discount,
                                    'total' => $item->unit_cost * $availableQty,
                                ]);
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('تم إنشاء مسودة المرتجع')
                            ->send();

                        return redirect()->route('filament.admin.resources.purchase-returns.edit', ['record' => $return->id]);
                    });
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => !$this->getRecord()->hasAssociatedRecords())
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->getRecord()->hasAssociatedRecords()) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('لا يمكن الحذف')
                            ->body('لا يمكن حذف الفاتورة لوجود حركات مخزون أو خزينة أو مدفوعات مرتبطة بها أو لأنها مؤكدة.')
                            ->send();

                        $action->halt();
                    }
                }),
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

        // Calculate discount
        $discountValue = floatval($data['discount_value'] ?? 0);
        $discountType = $data['discount_type'] ?? 'fixed';

        if ($discountType === 'percentage') {
            $calculatedDiscount = $subtotal * ($discountValue / 100);
        } else {
            $calculatedDiscount = $discountValue;
        }

        $total = $subtotal - $calculatedDiscount;

        // Ensure sanitized data is saved
        $data['discount'] = $calculatedDiscount;
        $data['discount_value'] = $discountValue;
        $data['subtotal'] = $subtotal;
        $data['total'] = $total;

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

                    // 1. Post stock movements (add stock)
                    app(\App\Services\StockService::class)->postPurchaseInvoice($record);

                    // 2. Post treasury transactions (deduct from treasury)
                    app(\App\Services\TreasuryService::class)->postPurchaseInvoice($record);

                    // 3. Update status back to posted (using saveQuietly to bypass model events)
                    $record->status = 'posted';
                    $record->saveQuietly();

                    // 4. Recalculate partner balance AFTER status is posted
                    // This is necessary because recalculateBalance() only sums 'posted' invoices
                    if ($record->partner_id) {
                        app(\App\Services\TreasuryService::class)->updatePartnerBalance($record->partner_id);
                    }
                });

                // Show success notification (outside transaction)
                \Filament\Notifications\Notification::make()
                    ->title('تم ترحيل الفاتورة وتحديث المخزون والخزينة')
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

                // Re-throw to prevent the page from showing success (REMOVED to show toast instead of error page)
                // throw $e;
            }
        }
    }
}
