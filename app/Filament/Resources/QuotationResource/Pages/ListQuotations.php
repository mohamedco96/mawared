<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Filament\Resources\QuotationResource\Widgets\QuotationStatsOverview;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            QuotationStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->icon('heroicon-m-squares-2x2')
                ->badge(fn () => \App\Models\Quotation::count()),
            'pending' => Tab::make('معلق')
                ->icon('heroicon-m-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['draft', 'sent']))
                ->badge(fn () => \App\Models\Quotation::whereIn('status', ['draft', 'sent'])->count())
                ->badgeColor('warning'),
            'accepted' => Tab::make('مقبول')
                ->icon('heroicon-m-check-badge')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'accepted'))
                ->badge(fn () => \App\Models\Quotation::where('status', 'accepted')->count())
                ->badgeColor('success'),
            'converted' => Tab::make('محول لفاتورة')
                ->icon('heroicon-m-arrow-path')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'converted'))
                ->badge(fn () => \App\Models\Quotation::where('status', 'converted')->count())
                ->badgeColor('success'),
            'expired_rejected' => Tab::make('منتهي/مرفوض')
                ->icon('heroicon-m-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['expired', 'rejected']))
                ->badge(fn () => \App\Models\Quotation::whereIn('status', ['expired', 'rejected'])->count())
                ->badgeColor('danger'),
        ];
    }
}
