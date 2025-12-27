<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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
        $data['created_by'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        \Log::info('AfterCreate called', [
            'invoice_id' => $record->id,
            'status' => $record->status,
            'original_status' => $record->getOriginal('status'),
        ]);

        // Wait for the transaction to commit so items are visible
        DB::afterCommit(function () use ($record) {
            \Log::info('Inside DB::afterCommit', [
                'invoice_id' => $record->id,
                'status' => $record->status,
            ]);

            $record->refresh(); // Reload relations

            \Log::info('After refresh', [
                'invoice_id' => $record->id,
                'status' => $record->status,
                'original_status' => $record->getOriginal('status'),
                'items_count' => $record->items()->count(),
            ]);

            // Check if created as 'posted' directly
            if ($record->status === 'posted') {
                \Log::info('Status is posted, starting posting process');

                try {
                    // Wrap everything in a single transaction to ensure atomicity
                    DB::transaction(function () use ($record) {
                        // Temporarily set to draft for service validation
                        \Log::info('Before setting to draft', [
                            'status' => $record->status,
                            'original_status' => $record->getOriginal('status'),
                        ]);

                        $record->status = 'draft';

                        \Log::info('After setting to draft (in memory)', [
                            'status' => $record->status,
                            'original_status' => $record->getOriginal('status'),
                        ]);

                        // 1. Post stock movements (deduct stock)
                        \Log::info('Calling postSalesInvoice (stock)');
                        app(\App\Services\StockService::class)->postSalesInvoice($record);
                        \Log::info('Stock posted successfully');

                        // 2. Post treasury transactions (add to treasury)
                        \Log::info('Calling postSalesInvoice (treasury)');
                        app(\App\Services\TreasuryService::class)->postSalesInvoice($record);
                        \Log::info('Treasury posted successfully');

                        // 3. Update status back to posted (using saveQuietly to bypass model events)
                        \Log::info('Before saving as posted', [
                            'status' => $record->status,
                            'original_status' => $record->getOriginal('status'),
                        ]);

                        $record->status = 'posted';

                        \Log::info('Before saveQuietly', [
                            'status' => $record->status,
                            'original_status' => $record->getOriginal('status'),
                            'is_dirty' => $record->isDirty(),
                            'dirty_attributes' => $record->getDirty(),
                        ]);

                        $record->saveQuietly();

                        \Log::info('After saveQuietly', [
                            'status' => $record->status,
                            'original_status' => $record->getOriginal('status'),
                        ]);
                    });

                    // Show success notification (outside transaction)
                    \Filament\Notifications\Notification::make()
                        ->title('تم ترحيل الفاتورة وتحديث المخزون والخزينة')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    \Log::error('Error posting invoice', [
                        'invoice_id' => $record->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Transaction failed, all changes (stock movements, treasury transactions, status update) rolled back automatically
                    // Wait a moment for rollback to complete
                    usleep(100000); // 100ms

                    // Reload the record to get the current state from database
                    $record->refresh();

                    \Log::info('After transaction rollback', [
                        'invoice_id' => $record->id,
                        'status' => $record->status,
                        'stock_movements_count' => \App\Models\StockMovement::where('reference_id', $record->id)->count(),
                        'treasury_transactions_count' => \App\Models\TreasuryTransaction::where('reference_id', $record->id)->count(),
                    ]);

                    // IMPORTANT: Set invoice status back to 'draft' since posting failed
                    // The invoice was created with 'posted' status, but posting failed,
                    // so we need to revert it to 'draft' to reflect the actual state
                    if ($record->status === 'posted') {
                        \Log::info('Setting invoice status back to draft after failed posting', [
                            'invoice_id' => $record->id,
                            'current_status' => $record->status,
                        ]);

                        DB::transaction(function () use ($record) {
                            $record->status = 'draft';
                            $record->saveQuietly(); // Use saveQuietly to bypass model events
                        });

                        \Log::info('Invoice status reverted to draft', [
                            'invoice_id' => $record->id,
                            'new_status' => $record->fresh()->status,
                        ]);
                    }

                    // Show error notification
                    \Filament\Notifications\Notification::make()
                        ->title('خطأ في ترحيل الفاتورة')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }
            } else {
                \Log::info('Status is not posted, skipping');
            }
        });

        \Log::info('AfterCreate method completed');
    }
}
