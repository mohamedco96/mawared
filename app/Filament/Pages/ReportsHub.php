<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ReportsHub extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string $view = 'filament.pages.reports-hub';

    protected static ?string $navigationLabel = 'التقارير';

    protected static ?string $title = 'مركز التقارير';

    protected static ?string $navigationGroup = 'أخرى'; // Bottom group

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyPermission([
            'page_StockCard',
            'page_PartnerStatement',
            'page_ProfitLossReport',
            'page_ItemProfitabilityReport'
        ]) ?? false;
    }

    public ?string $activeReport = 'stock_card';

    public function mount(): void
    {
        // Check URL parameter for direct navigation
        $this->activeReport = request()->query('report', 'stock_card');

        // If specific filters passed (from contextual actions), dispatch event to prefill
        if (request()->has('product_id')) {
            $this->dispatch('prefill-stock-card', [
                'product_id' => request()->query('product_id')
            ]);
        }

        if (request()->has('partner_id')) {
            $this->dispatch('prefill-partner-statement', [
                'partner_id' => request()->query('partner_id')
            ]);
        }
    }

    public function setActiveReport(string $report): void
    {
        $this->activeReport = $report;
    }

    public function getReports(): array
    {
        $reports = [];

        if (auth()->user()?->can('page_StockCard')) {
            $reports['stock_card'] = [
                'label' => 'كارت الصنف',
                'icon' => 'heroicon-o-clipboard-document-list',
                'page' => \App\Filament\Pages\StockCard::class,
            ];
        }

        if (auth()->user()?->can('page_PartnerStatement')) {
            $reports['partner_statement'] = [
                'label' => 'كشف حساب عميل',
                'icon' => 'heroicon-o-document-text',
                'page' => \App\Filament\Pages\PartnerStatement::class,
            ];
        }

        if (auth()->user()?->can('page_ProfitLossReport')) {
            $reports['profit_loss'] = [
                'label' => 'المركز المالي وقائمة الدخل',
                'icon' => 'heroicon-o-chart-pie',
                'page' => \App\Filament\Pages\ProfitLossReport::class,
            ];
        }

        if (auth()->user()?->can('page_ItemProfitabilityReport')) {
            $reports['item_profitability'] = [
                'label' => 'تحليل ربحية الأصناف',
                'icon' => 'heroicon-o-currency-dollar',
                'page' => \App\Filament\Pages\ItemProfitabilityReport::class,
            ];
        }

        return $reports;
    }
}
