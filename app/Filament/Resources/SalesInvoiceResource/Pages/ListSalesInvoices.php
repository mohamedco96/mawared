<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

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
                ->badge(fn () => \App\Models\SalesInvoice::count()),
            'مسودة' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => \App\Models\SalesInvoice::where('status', 'draft')->count())
                ->badgeColor('warning'),
            'مؤكدة' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted'))
                ->badge(fn () => \App\Models\SalesInvoice::where('status', 'posted')->count())
                ->badgeColor('success'),
            'ديون' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted')->where('remaining_amount', '>', 0))
                ->badge(fn () => \App\Models\SalesInvoice::where('status', 'posted')->where('remaining_amount', '>', 0)->count())
                ->badgeColor('danger'),
        ];
    }
}
