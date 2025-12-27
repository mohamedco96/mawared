<?php

namespace App\Filament\Resources\SalesReturnResource\Pages;

use App\Filament\Resources\SalesReturnResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateSalesReturn extends CreateRecord
{
    protected static string $resource = SalesReturnResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

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

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        // Wait for the transaction to commit so items are visible
        DB::afterCommit(function () use ($record) {
            $record->refresh(); // Reload relations

            // Check if created as 'posted' directly
            if ($record->status === 'posted') {
                try {
                    // Wrap everything in a single transaction to ensure atomicity
                    DB::transaction(function () use ($record) {
                        // Temporarily set to draft for service validation
                        $record->status = 'draft';

                        // 1. Post stock movements (add stock back)
                        app(\App\Services\StockService::class)->postSalesReturn($record);

                        // 2. Post treasury transactions (deduct from treasury if cash)
                        app(\App\Services\TreasuryService::class)->postSalesReturn($record);

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
                }
            }
        });
    }
}
