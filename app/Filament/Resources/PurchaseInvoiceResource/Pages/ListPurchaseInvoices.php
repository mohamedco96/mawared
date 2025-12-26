<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'الكل' => Tab::make()
                ->badge(fn () => \App\Models\PurchaseInvoice::count()),
            'مسودة' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => \App\Models\PurchaseInvoice::where('status', 'draft')->count())
                ->badgeColor('warning'),
            'مؤكدة' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted'))
                ->badge(fn () => \App\Models\PurchaseInvoice::where('status', 'posted')->count())
                ->badgeColor('success'),
            'ديون' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted')->where('remaining_amount', '>', 0))
                ->badge(fn () => \App\Models\PurchaseInvoice::where('status', 'posted')->where('remaining_amount', '>', 0)->count())
                ->badgeColor('danger'),
        ];
    }
}
