<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

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
        // Items are saved automatically via relationship
    }
}
