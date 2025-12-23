<?php

namespace App\Filament\Pages;

use App\Models\SalesInvoice;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class ProfitLossReport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static string $view = 'filament.pages.profit-loss-report';

    protected static ?string $navigationLabel = 'تقرير الربح والخسارة';

    protected static ?string $title = 'تقرير الربح والخسارة';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public $from_date;

    public $to_date;

    public function mount(): void
    {
        $this->form->fill([
            'from_date' => now()->startOfMonth(),
            'to_date' => now()->endOfMonth(),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('فترة التقرير')
                    ->schema([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('من تاريخ')
                            ->required()
                            ->default(now()->startOfMonth()),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('إلى تاريخ')
                            ->required()
                            ->default(now()->endOfMonth()),
                    ])
                    ->columns(2),
            ]);
    }

    public $reportData = null;

    public function generateReport(): void
    {
        $data = $this->form->getState();
        $this->reportData = $this->calculateReport($data['from_date'], $data['to_date']);
    }

    protected function calculateReport($fromDate, $toDate): array
    {

        // Get all posted sales invoices in the period
        $salesInvoices = SalesInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->with('items.product')
            ->get();

        $totalSales = $salesInvoices->sum('total');
        
        // Calculate COGS from stock_movements (sales movements with cost_at_time)
        $salesMovements = StockMovement::where('type', 'sale')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->get();

        $totalCOGS = $salesMovements->sum(function ($movement) {
            // COGS = quantity (absolute) * cost_at_time
            return abs($movement->quantity) * $movement->cost_at_time;
        });

        $grossProfit = $totalSales - $totalCOGS;
        $profitMargin = $totalSales > 0 ? ($grossProfit / $totalSales) * 100 : 0;

        // Get expenses in the period
        $expenses = DB::table('treasury_transactions')
            ->where('type', 'expense')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('amount');

        $netProfit = $grossProfit + $expenses; // expenses are negative

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'total_sales' => $totalSales,
            'total_cogs' => $totalCOGS,
            'gross_profit' => $grossProfit,
            'profit_margin' => $profitMargin,
            'expenses' => abs($expenses),
            'net_profit' => $netProfit,
            'sales_count' => $salesInvoices->count(),
        ];
    }

    public function getReportDataProperty(): ?array
    {
        return $this->reportData;
    }
}
