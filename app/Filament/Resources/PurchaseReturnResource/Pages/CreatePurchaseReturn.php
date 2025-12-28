<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreatePurchaseReturn extends CreateRecord
{
    protected static string $resource = PurchaseReturnResource::class;

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

        \Log::info('AfterCreate called', [
            'return_id' => $record->id,
            'status' => $record->status,
            'original_status' => $record->getOriginal('status'),
        ]);

        // Wait for the transaction to commit so items are visible
        DB::afterCommit(function () use ($record) {
            \Log::info('Inside DB::afterCommit', [
                'return_id' => $record->id,
                'status' => $record->status,
            ]);

            $record->refresh(); // Reload relations

            \Log::info('After refresh', [
                'return_id' => $record->id,
                'status' => $record->status,
                'original_status' => $record->getOriginal('status'),
                'items_count' => $record->items()->count(),
            ]);

            // Check if created as 'posted' directly
            if ($record->status === 'posted') {
                \Log::info('Status is posted, starting posting process');

                try {
                    // Wrap everything in a single transaction to ensure atomicity
                    \Log::info('Starting main transaction', [
                        'transaction_level_before' => DB::transactionLevel(),
                        'return_id' => $record->id,
                        'paid_amount' => $record->paid_amount,
                        'total' => $record->total,
                    ]);

                    DB::transaction(function () use ($record) {
                        \Log::info('Inside main transaction', [
                            'transaction_level' => DB::transactionLevel(),
                            'return_id' => $record->id,
                        ]);

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

                        // Check stock movements before posting
                        $stockMovementsBefore = \App\Models\StockMovement::where('reference_id', $record->id)->count();
                        \Log::info('Before stock posting', [
                            'stock_movements_count' => $stockMovementsBefore,
                            'transaction_level' => DB::transactionLevel(),
                        ]);

                        // 1. Post stock movements (remove stock)
                        \Log::info('Calling postPurchaseReturn (stock)', [
                            'transaction_level' => DB::transactionLevel(),
                        ]);
                        app(\App\Services\StockService::class)->postPurchaseReturn($record);

                        // Check stock movements after posting (within transaction)
                        $stockMovementsAfter = \App\Models\StockMovement::where('reference_id', $record->id)->count();
                        \Log::info('Stock posted successfully', [
                            'stock_movements_before' => $stockMovementsBefore,
                            'stock_movements_after' => $stockMovementsAfter,
                            'transaction_level' => DB::transactionLevel(),
                        ]);

                        // Check treasury transactions before posting
                        $treasuryTransactionsBefore = \App\Models\TreasuryTransaction::where('reference_id', $record->id)->count();
                        \Log::info('Before treasury posting', [
                            'treasury_transactions_count' => $treasuryTransactionsBefore,
                            'transaction_level' => DB::transactionLevel(),
                        ]);

                        // 2. Post treasury transactions (add to treasury if cash)
                        \Log::info('Calling postPurchaseReturn (treasury)', [
                            'transaction_level' => DB::transactionLevel(),
                            'paid_amount' => $record->paid_amount,
                        ]);
                        app(\App\Services\TreasuryService::class)->postPurchaseReturn($record);

                        // Check treasury transactions after posting (within transaction)
                        $treasuryTransactionsAfter = \App\Models\TreasuryTransaction::where('reference_id', $record->id)->count();
                        \Log::info('Treasury posted successfully', [
                            'treasury_transactions_before' => $treasuryTransactionsBefore,
                            'treasury_transactions_after' => $treasuryTransactionsAfter,
                            'transaction_level' => DB::transactionLevel(),
                        ]);

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
                            'transaction_level' => DB::transactionLevel(),
                        ]);
                    });

                    \Log::info('Main transaction committed successfully', [
                        'transaction_level_after' => DB::transactionLevel(),
                        'return_id' => $record->id,
                    ]);

                    // Show success notification (outside transaction)
                    \Filament\Notifications\Notification::make()
                        ->title('تم ترحيل المرتجع وتحديث المخزون والخزينة')
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    \Log::error('Error posting return - Exception caught', [
                        'return_id' => $record->id,
                        'error' => $e->getMessage(),
                        'error_class' => get_class($e),
                        'transaction_level' => DB::transactionLevel(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);

                    // Transaction failed, all changes rolled back automatically
                    usleep(100000); // 100ms

                    // Reload the record to get the current state from database
                    $record->refresh();

                    // Check actual database state after rollback
                    $stockMovementsAfterRollback = \App\Models\StockMovement::where('reference_id', $record->id)->get();
                    $treasuryTransactionsAfterRollback = \App\Models\TreasuryTransaction::where('reference_id', $record->id)->get();

                    \Log::info('After transaction rollback - Database state', [
                        'return_id' => $record->id,
                        'status' => $record->status,
                        'status_in_db' => \App\Models\PurchaseReturn::find($record->id)?->status,
                        'stock_movements_count' => $stockMovementsAfterRollback->count(),
                        'stock_movements_ids' => $stockMovementsAfterRollback->pluck('id')->toArray(),
                        'treasury_transactions_count' => $treasuryTransactionsAfterRollback->count(),
                        'treasury_transactions_ids' => $treasuryTransactionsAfterRollback->pluck('id')->toArray(),
                        'transaction_level' => DB::transactionLevel(),
                    ]);

                    // Also check product stock levels to see if they were rolled back
                    foreach ($record->items as $item) {
                        $product = $item->product;
                        $currentStock = app(\App\Services\StockService::class)->getCurrentStock($record->warehouse_id, $product->id);
                        \Log::info('Product stock after rollback', [
                            'product_id' => $product->id,
                            'product_name' => $product->name,
                            'warehouse_id' => $record->warehouse_id,
                            'current_stock' => $currentStock,
                            'return_quantity' => $item->quantity,
                        ]);
                    }

                    // IMPORTANT: Set return status back to 'draft' since posting failed
                    if ($record->status === 'posted') {
                        \Log::info('Setting return status back to draft after failed posting', [
                            'return_id' => $record->id,
                            'current_status' => $record->status,
                        ]);

                        DB::transaction(function () use ($record) {
                            $record->status = 'draft';
                            $record->saveQuietly();
                        });

                        \Log::info('Return status reverted to draft', [
                            'return_id' => $record->id,
                            'new_status' => $record->fresh()->status,
                        ]);
                    }

                    // Show error notification
                    \Filament\Notifications\Notification::make()
                        ->title('خطأ في ترحيل المرتجع')
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
