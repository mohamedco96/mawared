<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('create_return')
                ->label('إنشاء مرتجع')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn () => $this->record->isPosted())
                ->action(function () {
                    // Create a new purchase return based on this invoice
                    $invoice = $this->record;

                    $return = PurchaseReturn::create([
                        'return_number' => 'RET-PURCHASE-' . now()->format('Ymd') . '-' . Str::random(6),
                        'warehouse_id' => $invoice->warehouse_id,
                        'partner_id' => $invoice->partner_id,
                        'status' => 'draft',
                        'payment_method' => $invoice->payment_method,
                        'subtotal' => $invoice->subtotal,
                        'discount' => $invoice->discount,
                        'total' => $invoice->total,
                        'notes' => 'مرتجع من الفاتورة: ' . $invoice->invoice_number,
                        'created_by' => auth()->id(),
                    ]);

                    // Replicate items (using unit_cost instead of unit_price)
                    foreach ($invoice->items as $item) {
                        PurchaseReturnItem::create([
                            'purchase_return_id' => $return->id,
                            'product_id' => $item->product_id,
                            'unit_type' => $item->unit_type,
                            'quantity' => $item->quantity,
                            'unit_cost' => $item->unit_cost,
                            'discount' => $item->discount,
                            'total' => $item->total,
                        ]);
                    }

                    // Redirect to edit the return
                    return redirect()->route('filament.admin.resources.purchase-returns.edit', ['record' => $return->id]);
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
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Items are saved automatically via relationship
    }
}
