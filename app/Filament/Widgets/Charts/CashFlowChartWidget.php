<?php

namespace App\Filament\Widgets\Charts;

use App\Models\TreasuryTransaction;
use Filament\Widgets\ChartWidget;

class CashFlowChartWidget extends ChartWidget
{
    protected static ?string $heading = 'التدفق النقدي التشغيلي (آخر 30 يوم)';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $maxHeight = '300px';

    protected static bool $isLazy = true;

    protected static ?string $pollingInterval = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $dates = [];
        $incomeData = [];
        $expenseData = [];

        // Loop through last 30 days
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $dates[] = now()->subDays($i)->format('M d');

            // OPERATING INCOME: collection + income from operating activities
            $income = TreasuryTransaction::whereDate('created_at', $date)
                ->where(function ($q) {
                    $q->where(function ($query) {
                        $query->where('type', 'collection')
                            ->where(function ($subQuery) {
                                $subQuery->whereIn('reference_type', ['sales_invoice', 'sales_return', 'revenue', 'financial_transaction'])
                                    ->orWhereNull('reference_type');
                            });
                    })
                    ->orWhere(function ($query) {
                        $query->where('type', 'income')
                            ->where(function ($subQuery) {
                                $subQuery->whereIn('reference_type', ['revenue', 'financial_transaction'])
                                    ->orWhereNull('reference_type');
                            });
                    });
                })
                ->sum('amount') ?? 0;

            // OPERATING EXPENSE: payment + expense from operating activities
            $expense = TreasuryTransaction::whereDate('created_at', $date)
                ->where(function ($q) {
                    $q->where(function ($query) {
                        $query->where('type', 'payment')
                            ->where(function ($subQuery) {
                                $subQuery->whereIn('reference_type', ['purchase_invoice', 'purchase_return', 'expense', 'financial_transaction'])
                                    ->orWhereNull('reference_type');
                            });
                    })
                    ->orWhere(function ($query) {
                        $query->where('type', 'expense')
                            ->where(function ($subQuery) {
                                $subQuery->whereIn('reference_type', ['expense', 'financial_transaction'])
                                    ->orWhereNull('reference_type');
                            });
                    });
                })
                ->sum('amount') ?? 0;

            $incomeData[] = (float) $income;
            $expenseData[] = (float) abs($expense);
        }

        return [
            'datasets' => [
                [
                    'label' => 'الإيرادات',
                    'data' => $incomeData,
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#10b981',
                ],
                [
                    'label' => 'المصروفات',
                    'data' => $expenseData,
                    'backgroundColor' => '#ef4444',
                    'borderColor' => '#ef4444',
                ],
            ],
            'labels' => $dates,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'font' => [
                            'family' => 'Cairo',
                            'size' => 12,
                        ],
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'font' => [
                            'family' => 'Cairo',
                        ],
                    ],
                ],
                'x' => [
                    'ticks' => [
                        'font' => [
                            'family' => 'Cairo',
                        ],
                    ],
                ],
            ],
        ];
    }
}
