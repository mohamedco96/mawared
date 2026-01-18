<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProductCategory extends EditRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action) {
                    if ($this->getRecord()->hasAssociatedRecords()) {
                        Notification::make()
                            ->danger()
                            ->title('لا يمكن حذف التصنيف')
                            ->body('لا يمكن حذف التصنيف لوجود منتجات أو تصنيفات فرعية مرتبطة به.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'تم تحديث التصنيف بنجاح';
    }

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('خطأ في تحديث التصنيف')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
