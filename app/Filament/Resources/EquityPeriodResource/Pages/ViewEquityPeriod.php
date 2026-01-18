<?php

namespace App\Filament\Resources\EquityPeriodResource\Pages;

use App\Filament\Resources\EquityPeriodResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewEquityPeriod extends ViewRecord
{
    protected static string $resource = EquityPeriodResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('معلومات الفترة')
                    ->schema([
                        Infolists\Components\TextEntry::make('period_number')
                            ->label('رقم الفترة')
                            ->badge(),
                        Infolists\Components\TextEntry::make('start_date')
                            ->label('تاريخ البداية')
                            ->date('Y-m-d'),
                        Infolists\Components\TextEntry::make('end_date')
                            ->label('تاريخ النهاية')
                            ->date('Y-m-d')
                            ->placeholder('مفتوحة'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('الحالة')
                            ->formatStateUsing(fn ($state) => $state === 'open' ? 'مفتوحة' : 'مغلقة')
                            ->badge()
                            ->color(fn ($state) => $state === 'open' ? 'success' : 'gray'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('الملخص المالي')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_revenue')
                            ->label('إجمالي الإيرادات')
                            ->money('EGP'),
                        Infolists\Components\TextEntry::make('total_expenses')
                            ->label('إجمالي المصروفات')
                            ->money('EGP'),
                        Infolists\Components\TextEntry::make('net_profit')
                            ->label('صافي الربح')
                            ->money('EGP')
                            ->color(fn ($state) => $state > 0 ? 'success' : ($state < 0 ? 'danger' : 'gray')),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('نسب الشركاء')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('partners')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('الشريك'),
                                Infolists\Components\TextEntry::make('pivot.equity_percentage')
                                    ->label('نسبة الملكية')
                                    ->formatStateUsing(fn ($state) => number_format($state, 2) . '%')
                                    ->badge()
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('pivot.capital_at_start')
                                    ->label('رأس المال في البداية')
                                    ->money('EGP')
                                    ->formatStateUsing(function ($record) {
                                        // Total capital = capital at start + capital injected during period
                                        $capitalAtStart = $record->pivot->capital_at_start ?? 0;
                                        $capitalInjected = $record->pivot->capital_injected ?? 0;
                                        return $capitalAtStart + $capitalInjected;
                                    }),
                                Infolists\Components\TextEntry::make('pivot.profit_allocated')
                                    ->label('الربح المخصص')
                                    ->money('EGP')
                                    ->badge()
                                    ->color('success'),
                            ])
                            ->columns(4),
                    ]),

                Infolists\Components\Section::make('معلومات الإغلاق')
                    ->schema([
                        Infolists\Components\TextEntry::make('closedBy.name')
                            ->label('أُغلقت بواسطة')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('closed_at')
                            ->label('تاريخ الإغلاق')
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('—'),
                        Infolists\Components\TextEntry::make('notes')
                            ->label('ملاحظات')
                            ->columnSpanFull()
                            ->placeholder('—'),
                    ])
                    ->visible(fn ($record) => $record->status === 'closed')
                    ->columns(2),
            ]);
    }
}
