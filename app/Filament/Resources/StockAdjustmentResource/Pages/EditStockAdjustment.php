<?php

namespace App\Filament\Resources\StockAdjustmentResource\Pages;

use App\Filament\Resources\StockAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockAdjustment extends EditRecord
{
    protected static string $resource = StockAdjustmentResource::class;
    
    protected function beforeSave(): void
    {
        if ($this->getRecord()->isPosted()) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('لا يمكن تعديل التسوية')
                ->body('نأسف، لا يمكن تعديل تسوية المخزون بعد التأكيد')
                ->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->getRecord()->hasAssociatedRecords())
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->getRecord()->hasAssociatedRecords()) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('لا يمكن الحذف')
                            ->body('لا يمكن حذف حركة المخزون لأنها مؤكدة أو لها حركات مخزون مرتبطة.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
