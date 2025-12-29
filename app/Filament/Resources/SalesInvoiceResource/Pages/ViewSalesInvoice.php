<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('طباعة PDF')
                ->icon('heroicon-o-printer')
                ->url(fn () => route('invoices.sales.print', $this->record))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->isPosted())
                ->color('success'),
            Actions\EditAction::make()
                ->visible(fn () => $this->record->isDraft()),
        ];
    }
}
