<?php

namespace App\Filament\Resources\FixedAssetResource\Pages;

use App\Filament\Resources\FixedAssetResource;
use App\Services\TreasuryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateFixedAsset extends CreateRecord
{
    protected static string $resource = FixedAssetResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft'; // Always create as draft first

        // Ensure treasury_id is set for non-cash methods
        if ($data['funding_method'] !== 'cash') {
            // Set default treasury for reference purposes
            $data['treasury_id'] = $data['treasury_id'] ?? \App\Models\Treasury::first()?->id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Automatically post the fixed asset after creation
        $treasuryService = app(TreasuryService::class);

        try {
            DB::transaction(function () use ($treasuryService) {
                $treasuryService->postFixedAssetPurchase($this->record);
            });

            Notification::make()
                ->success()
                ->title('تم التسجيل بنجاح')
                ->body('تم تسجيل الأصل الثابت وتطبيق محاسبة القيد المزدوج')
                ->send();
        } catch (\Exception $e) {
            // If posting fails, the record is still created as draft
            Notification::make()
                ->warning()
                ->title('تم إنشاء الأصل كمسودة')
                ->body('الأصل الثابت تم إنشاؤه ولكن لم يتم تسجيله: ' . $e->getMessage())
                ->send();
        }
    }
}
