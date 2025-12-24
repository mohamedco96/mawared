<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\GeneralSetting;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\Treasury;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Generate comprehensive financial report for date range
     */
    public function generateReport($fromDate, $toDate): array
    {
        // Get settings
        $initialCapital = (float) GeneralSetting::getValue('initial_capital', '0');
        $fixedAssetsValue = (float) GeneralSetting::getValue('fixed_assets_value', '0');

        // Inventory calculations
        $endingInventory = $this->calculateInventoryValue($toDate);
        $beginningInventory = $this->calculateInventoryValue($fromDate, true);

        // Partners calculations
        $totalDebtors = $this->calculateTotalDebtors();
        $totalCreditors = $this->calculateTotalCreditors();

        // Treasury calculations
        $totalCash = $this->calculateTotalCash();

        // Trading calculations (Income Statement)
        $totalSales = $this->calculateTotalSales($fromDate, $toDate);
        $totalPurchases = $this->calculateTotalPurchases($fromDate, $toDate);
        $salesReturns = $this->calculateSalesReturns($fromDate, $toDate);
        $purchaseReturns = $this->calculatePurchaseReturns($fromDate, $toDate);
        $expenses = $this->calculateExpenses($fromDate, $toDate);
        $revenues = $this->calculateRevenues($fromDate, $toDate);

        // Income Statement calculations
        $debitTotal = $beginningInventory + $totalPurchases + $salesReturns + $expenses;
        $creditTotal = $endingInventory + $totalSales + $purchaseReturns + $revenues;
        $netProfit = $creditTotal - $debitTotal;

        // Financial Position calculations
        $totalAssets = $fixedAssetsValue + $endingInventory + $totalDebtors + $totalCash;
        $totalLiabilities = $initialCapital + $netProfit + $totalCreditors;

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'initial_capital' => $initialCapital,
            'fixed_assets_value' => $fixedAssetsValue,
            'beginning_inventory' => $beginningInventory,
            'ending_inventory' => $endingInventory,
            'total_debtors' => $totalDebtors,
            'total_creditors' => $totalCreditors,
            'total_cash' => $totalCash,
            'total_sales' => $totalSales,
            'total_purchases' => $totalPurchases,
            'sales_returns' => $salesReturns,
            'purchase_returns' => $purchaseReturns,
            'expenses' => $expenses,
            'revenues' => $revenues,
            'net_profit' => $netProfit,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
        ];
    }

    /**
     * Calculate inventory value at a specific date
     * 
     * @param string $date The date to calculate inventory up to
     * @param bool $exclusive If true, use < instead of <= for date comparison
     */
    protected function calculateInventoryValue(string $date, bool $exclusive = false): float
    {
        // Get all products
        $products = Product::all();

        $totalValue = 0;

        foreach ($products as $product) {
            // Calculate current stock quantity up to the date
            $query = StockMovement::where('product_id', $product->id);
            
            if ($exclusive) {
                $query->where('created_at', '<', $date);
            } else {
                $query->where('created_at', '<=', $date);
            }
            
            $stockQuantity = $query->sum('quantity');

            // Only calculate value if we have stock
            if ($stockQuantity > 0 && $product->avg_cost > 0) {
                $totalValue += $stockQuantity * $product->avg_cost;
            }
        }

        return $totalValue;
    }

    /**
     * Calculate total debtors (customers with positive balances)
     */
    protected function calculateTotalDebtors(): float
    {
        return Partner::where('type', 'customer')
            ->where('current_balance', '>', 0)
            ->sum('current_balance');
    }

    /**
     * Calculate total creditors (suppliers with negative balances, returned as absolute value)
     */
    protected function calculateTotalCreditors(): float
    {
        return abs(Partner::where('type', 'supplier')
            ->where('current_balance', '<', 0)
            ->sum('current_balance'));
    }

    /**
     * Calculate total cash from all treasuries
     */
    protected function calculateTotalCash(): float
    {
        $treasuryService = app(TreasuryService::class);
        $treasuries = Treasury::all();

        $total = 0;
        foreach ($treasuries as $treasury) {
            $balance = (float) $treasuryService->getTreasuryBalance($treasury->id);
            $total += $balance;
        }

        return $total;
    }

    /**
     * Calculate total sales in date range
     */
    protected function calculateTotalSales($fromDate, $toDate): float
    {
        return SalesInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');
    }

    /**
     * Calculate total purchases in date range
     */
    protected function calculateTotalPurchases($fromDate, $toDate): float
    {
        return PurchaseInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');
    }

    /**
     * Calculate sales returns in date range
     */
    protected function calculateSalesReturns($fromDate, $toDate): float
    {
        return SalesReturn::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');
    }

    /**
     * Calculate purchase returns in date range
     */
    protected function calculatePurchaseReturns($fromDate, $toDate): float
    {
        return PurchaseReturn::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->sum('total');
    }

    /**
     * Calculate expenses in date range
     */
    protected function calculateExpenses($fromDate, $toDate): float
    {
        return Expense::whereBetween('expense_date', [$fromDate, $toDate])
            ->sum('amount');
    }

    /**
     * Calculate revenues in date range
     */
    protected function calculateRevenues($fromDate, $toDate): float
    {
        return Revenue::whereBetween('revenue_date', [$fromDate, $toDate])
            ->sum('amount');
    }
}

