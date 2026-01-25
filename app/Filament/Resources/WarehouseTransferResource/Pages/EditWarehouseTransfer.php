<?php

namespace App\Filament\Resources\WarehouseTransferResource\Pages;

use App\Filament\Resources\WarehouseTransferResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseTransfer extends EditRecord
{
    protected static string $resource = WarehouseTransferResource::class;
    
    protected function beforeSave(): void
    {
        if ($this->getRecord()->stockMovements()->exists()) {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('لا يمكن تعديل نقل المخزون')
                ->body('نأسف، لا يمكن تعديل حركة النقل بعد تأكيدها')
                ->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => !$this->getRecord()->stockMovements()->exists()),
        ];
    }
}
