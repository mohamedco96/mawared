<?php

namespace App\Filament\Resources\ProductCategoryResource\Pages;

use App\Filament\Resources\ProductCategoryResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProductCategory extends CreateRecord
{
    protected static string $resource = ProductCategoryResource::class;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'تم إنشاء التصنيف بنجاح';
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('خطأ في إنشاء التصنيف')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            $this->halt();
        }
    }
}
