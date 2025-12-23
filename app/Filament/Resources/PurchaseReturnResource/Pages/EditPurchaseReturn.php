<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

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
}
