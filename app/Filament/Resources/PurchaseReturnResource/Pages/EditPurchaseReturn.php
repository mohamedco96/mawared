<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditPurchaseReturn extends EditRecord
{
    protected static string $resource = PurchaseReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        $record = $this->record;

        // Check if status was changed from draft to posted
        if ($record->wasChanged('status') && $record->status === 'posted') {
            try {
                // Wrap everything in a single transaction to ensure atomicity
                DB::transaction(function () use ($record) {
                    // Temporarily set to draft for service validation
                    $record->status = 'draft';

                    // 1. Post stock movements (remove stock)
                    app(\App\Services\StockService::class)->postPurchaseReturn($record);

                    // 2. Post treasury transactions (add to treasury if cash)
                    app(\App\Services\TreasuryService::class)->postPurchaseReturn($record);

                    // 3. Update status back to posted (using saveQuietly to bypass model events)
                    $record->status = 'posted';
                    $record->saveQuietly();
                });

                // Show success notification (outside transaction)
                \Filament\Notifications\Notification::make()
                    ->title('تم ترحيل المرتجع وتحديث المخزون والخزينة')
                    ->success()
                    ->send();
            } catch (\Exception $e) {
                // Transaction failed, all changes rolled back automatically
                usleep(100000); // 100ms

                // Reload the record to get the current state from database
                $record->refresh();

                // IMPORTANT: Set return status back to 'draft' since posting failed
                if ($record->status === 'posted') {
                    DB::transaction(function () use ($record) {
                        $record->status = 'draft';
                        $record->saveQuietly();
                    });
                }

                // Show error notification
                \Filament\Notifications\Notification::make()
                    ->title('خطأ في ترحيل المرتجع')
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
