<?php

namespace App\Filament\Widgets;

use App\Models\Partner;
use App\Models\TreasuryTransaction;
use App\Services\FinancialReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class FinancialOverviewWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return Cache::remember('dashboard.financial_overview', 300, function () {
            // 1. Total Cash: Sum of all treasury transactions
            $totalCash = TreasuryTransaction::sum('amount') ?? 0;

            // 2. Receivables: Customers who owe us (positive balance)
            $receivables = Partner::where('type', 'customer')
                ->where('current_balance', '>', 0)
                ->sum('current_balance') ?? 0;

            // 3. Payables: Suppliers we owe (negative balance, display as positive)
            $payables = abs(Partner::where('type', 'supplier')
                ->where('current_balance', '<', 0)
                ->sum('current_balance')) ?? 0;

            // 4. Net Profit: Current month from FinancialReportService
            $service = app(FinancialReportService::class);
            $report = $service->generateReport(
                now()->startOfMonth()->format('Y-m-d'),
                now()->endOfMonth()->format('Y-m-d')
            );
            $netProfit = $report['net_profit'] ?? 0;

            return [
                Stat::make('إجمالي الرصيد النقدي', number_format($totalCash, 2) . ' ج.م')
                    ->description('رصيد جميع الخزائن')
                    ->icon('heroicon-o-banknotes')
                    ->color('success'),

                Stat::make('فلوس لينا - المدينون', number_format($receivables, 2) . ' ج.م')
                    ->description('العملاء اللي لينا فلوس عندهم')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('info'),

                Stat::make('فلوس علينا - الدائنون', number_format($payables, 2) . ' ج.م')
                    ->description('الموردين اللي علينا فلوس ليهم')
                    ->icon('heroicon-o-arrow-trending-down')
                    ->color('danger'),

                Stat::make('صافي الربح - الشهر الحالي', number_format($netProfit, 2) . ' ج.م')
                    ->description('من ' . now()->startOfMonth()->format('d/m') . ' إلى ' . now()->format('d/m'))
                    ->icon('heroicon-o-chart-bar')
                    ->color('warning'),
            ];
        });
    }
}
