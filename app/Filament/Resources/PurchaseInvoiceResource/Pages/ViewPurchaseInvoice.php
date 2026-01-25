<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\PurchaseInvoice;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->isDraft()),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => !$record->hasAssociatedRecords()),
            Actions\Action::make('create_return')
                ->label('إنشاء مرتجع')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (PurchaseInvoice $record) => $record->isPosted() && !$record->isPostedFullyReturned())
                ->requiresConfirmation()
                ->modalHeading('إنشاء مرتجع مشتريات')
                ->modalDescription(fn (PurchaseInvoice $record) => $record->returns()->where('status', 'draft')->exists()
                    ? '⚠️ تنبيه: توجد مسودات مرتجعات سابقة لهذه الفاتورة. هل تريد إنشاء مسودة جديدة على أي حال؟ (سيتم تحميل الكميات المتبقية فقط)'
                    : 'سيتم إنشاء مسودة مرتجع بناءً على هذه الفاتورة. هل أنت متأكد؟')
                ->action(function (PurchaseInvoice $record) {
                    return DB::transaction(function () use ($record) {
                        $return = PurchaseReturn::create([
                            'return_number' => 'RET-PURCHASE-' . now()->format('Ymd') . '-' . Str::random(6),
                            'warehouse_id' => $record->warehouse_id,
                            'partner_id' => $record->partner_id,
                            'purchase_invoice_id' => $record->id,
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
        ];
    }
}
