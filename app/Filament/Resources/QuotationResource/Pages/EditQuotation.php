<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQuotation extends EditRecord
{
    protected static string $resource = QuotationResource::class;
    
    protected function beforeSave(): void
    {
        if ($this->getRecord()->status === 'converted') {
            \Filament\Notifications\Notification::make()
                ->warning()
                ->title('لا يمكن تعديل عرض السعر')
                ->body('عرض السعر تم تحويله إلى فاتورة ولا يمكن تعديله')
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
                            ->body('لا يمكن حذف عرض السعر لأنه محول إلى فاتورة.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }
}
