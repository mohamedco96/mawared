<?php

namespace App\Filament\Resources\PurchaseReturnResource\Pages;

use App\Filament\Resources\PurchaseReturnResource;
use Filament\Resources\Pages\CreateRecord;

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
}
