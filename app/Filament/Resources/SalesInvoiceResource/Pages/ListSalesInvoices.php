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

    protected function getHeaderWidgets(): array
    {
        return [
            SalesInvoiceResource\Widgets\SalesInvoiceStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\SalesInvoice::count()),
            'draft' => Tab::make('مسودة')
                ->icon('heroicon-m-document-text')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge(fn () => \App\Models\SalesInvoice::where('status', 'draft')->count())
                ->badgeColor('warning'),
            'posted' => Tab::make('مؤكدة')
                ->icon('heroicon-m-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted'))
                ->badge(fn () => \App\Models\SalesInvoice::where('status', 'posted')->count())
                ->badgeColor('success'),
            'unpaid' => Tab::make('آجل / ديون')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'posted')->where('remaining_amount', '>', 0))
                ->badge(fn () => \App\Models\SalesInvoice::where('status', 'posted')->where('remaining_amount', '>', 0)->count())
                ->badgeColor('danger'),
        ];
    }
}
