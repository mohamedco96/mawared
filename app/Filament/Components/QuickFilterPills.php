<?php

namespace App\Filament\Components;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;

class QuickFilterPills
{
    /**
     * Get quick filter pills for invoices and similar resources
     */
    public static function make(): array
    {
        return [
            Filter::make('today')
                ->label('اليوم')
                ->query(fn ($query) => $query->whereDate('created_at', today()))
                ->toggle()
                ->default(false),

            Filter::make('this_week')
                ->label('هذا الأسبوع')
                ->query(fn ($query) => $query->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]))
                ->toggle()
                ->default(false),

            Filter::make('this_month')
                ->label('هذا الشهر')
                ->query(fn ($query) => $query->whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year))
                ->toggle()
                ->default(false),

            Filter::make('last_30_days')
                ->label('آخر 30 يوم')
                ->query(fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
                ->toggle()
                ->default(false),
        ];
    }

    /**
     * Get unpaid filter for invoices
     */
    public static function unpaidFilter(): TernaryFilter
    {
        return TernaryFilter::make('unpaid')
            ->label('حالة الدفع')
            ->placeholder('الكل')
            ->trueLabel('غير مدفوع فقط')
            ->falseLabel('مدفوع فقط')
            ->queries(
                true: fn ($query) => $query->where('remaining_amount', '>', 0),
                false: fn ($query) => $query->where('remaining_amount', '<=', 0),
            );
    }

    /**
     * Get draft filter for documents
     */
    public static function draftFilter(): TernaryFilter
    {
        return TernaryFilter::make('draft_status')
            ->label('حالة المستند')
            ->placeholder('الكل')
            ->trueLabel('مسودات فقط')
            ->falseLabel('مؤكدة فقط')
            ->queries(
                true: fn ($query) => $query->where('status', 'draft'),
                false: fn ($query) => $query->where('status', 'posted'),
            );
    }
}
