<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseInvoiceResource\Widgets\PurchaseInvoiceStatsOverview;
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

    protected function getHeaderWidgets(): array
    {
        return [
            PurchaseInvoiceStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\PurchaseInvoice::count()),
            'draft' => Tab::make('مسودة')
                ->icon('heroicon-m-document')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => \App\Models\PurchaseInvoice::where('status', 'draft')->count())
                ->badgeColor('warning'),
            'posted' => Tab::make('مؤكدة')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted'))
                ->badge(fn () => \App\Models\PurchaseInvoice::where('status', 'posted')->count())
                ->badgeColor('success'),
            'debt' => Tab::make('ديون')
                ->icon('heroicon-m-banknotes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted')->where('remaining_amount', '>', 0))
                ->badge(fn () => \App\Models\PurchaseInvoice::where('status', 'posted')->where('remaining_amount', '>', 0)->count())
                ->badgeColor('danger'),
        ];
    }
}
