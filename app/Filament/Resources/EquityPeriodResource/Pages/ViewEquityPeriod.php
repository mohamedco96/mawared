<?php

namespace App\Filament\Resources\EquityPeriodResource\Pages;

use App\Filament\Resources\EquityPeriodResource;
use App\Services\CapitalService;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewEquityPeriod extends ViewRecord
{
    protected static string $resource = EquityPeriodResource::class;

    protected ?array $liveFinancialSummary = null;

    /**
     * Get live financial summary for open periods
     */
    protected function getLiveFinancialSummary(): array
    {
        if ($this->liveFinancialSummary === null) {
            if ($this->record->status === 'open') {
                $this->liveFinancialSummary = app(CapitalService::class)->getFinancialSummary($this->record);
            } else {
                $this->liveFinancialSummary = [
                    'total_revenue' => $this->record->total_revenue,
                    'total_expenses' => $this->record->total_expenses,
                    'net_profit' => $this->record->net_profit,
                ];
            }
        }

        return $this->liveFinancialSummary;
    }

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
                            ->dateTime('Y-m-d H:i:s'),
                        Infolists\Components\TextEntry::make('end_date')
                            ->label('تاريخ النهاية')
                            ->dateTime('Y-m-d H:i:s')
                            ->placeholder('مفتوحة'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('الحالة')
                            ->formatStateUsing(fn ($state) => $state === 'open' ? 'مفتوحة' : 'مغلقة')
                            ->badge()
                            ->color(fn ($state) => $state === 'open' ? 'success' : 'gray'),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('الملخص المالي')
                    ->description(fn ($record) => $record->status === 'open' ? 'يتم حساب القيم تلقائياً من البيانات الحالية' : null)
                    ->schema([
                        Infolists\Components\TextEntry::make('total_revenue')
                            ->label('إجمالي الإيرادات')
                            ->state(fn () => $this->getLiveFinancialSummary()['total_revenue'])
                            ->money('EGP'),
                        Infolists\Components\TextEntry::make('total_expenses')
                            ->label('إجمالي المصروفات')
                            ->state(fn () => $this->getLiveFinancialSummary()['total_expenses'])
                            ->money('EGP'),
                        Infolists\Components\TextEntry::make('net_profit')
                            ->label('صافي الربح')
                            ->state(fn () => $this->getLiveFinancialSummary()['net_profit'])
                            ->money('EGP')
                            ->color(fn () => $this->getLiveFinancialSummary()['net_profit'] > 0 ? 'success' : ($this->getLiveFinancialSummary()['net_profit'] < 0 ? 'danger' : 'gray')),
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
