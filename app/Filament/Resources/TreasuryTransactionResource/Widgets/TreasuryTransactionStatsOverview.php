<?php

namespace App\Filament\Resources\TreasuryTransactionResource\Widgets;

use App\Enums\TransactionType;
use App\Models\TreasuryTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TreasuryTransactionStatsOverview extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static string $view = 'filament.resources.treasury-transaction-resource.widgets.treasury-transaction-stats-overview';

    protected function getStats(): array
    {
        // Define income and expense types
        // This should ideally come from TransactionType enum if it has methods to categorize
        // For now, I'll list them based on standard logic
        $incomeTypes = [
            'capital_deposit', 'collection', 'partner_loan_receipt', 'other_income'
        ];
        
        $expenseTypes = [
            'payment', 'expense', 'partner_drawing', 'employee_advance', 'salary_payment', 'partner_loan_repayment', 'other_expense'
        ];

        // Or use positive/negative amounts if stored that way. 
        // Typically TreasuryTransaction stores absolute amount and type determines sign.
        // But usually for stats we want total In vs total Out.
        
        // Let's assume we can filter by type.
        
        // Total Income
        $totalIncome = TreasuryTransaction::whereIn('type', $incomeTypes)->sum('amount');
        
        // Total Expense
        $totalExpense = TreasuryTransaction::whereIn('type', $expenseTypes)->sum('amount');
        
        // Net Flow
        $netFlow = $totalIncome - $totalExpense;

        return [
            Stat::make('صافي التدفقات', number_format($netFlow, 2))
                ->description('الفرق بين المقبوضات والمدفوعات')
                ->descriptionIcon('heroicon-m-scale')
                ->color($netFlow >= 0 ? 'success' : 'danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('إجمالي المقبوضات', number_format($totalIncome, 2))
                ->description('إجمالي الأموال الواردة')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),

            Stat::make('إجمالي المدفوعات', number_format($totalExpense, 2))
                ->description('إجمالي الأموال الصادرة')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'financial-value-stat financial-blur',
                    'x-data' => '{ show: false }',
                    'x-on:mouseenter' => 'show = true',
                    'x-on:mouseleave' => 'show = false',
                    ':class' => '{ "financial-blur": !show }',
                ]),
        ];
    }
}
