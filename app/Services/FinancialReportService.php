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
use App\Models\TreasuryTransaction;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Generate comprehensive financial report for date range
     */
    public function generateReport($fromDate, $toDate): array
    {
        // Get settings
        $shareholderCapital = $this->calculateShareholderCapital();

        // Calculate fixed assets value
        $fixedAssetsValue = $this->calculateFixedAssetsValue();

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
        $salesReturns = $this->calculateSalesReturns($fromDate, $toDate);

        // CRITICAL CHANGE: Use COGS instead of Total Purchases
        $costOfGoodsSold = $this->calculateCOGS($fromDate, $toDate);

        $expenses = $this->calculateExpenses($fromDate, $toDate);
        $revenues = $this->calculateRevenues($fromDate, $toDate);

        // NEW: Include commissions in expenses
        $commissionsPaid = $this->calculateCommissionsPaid($fromDate, $toDate);

        // Settlement discount calculations (payment-time discounts)
        // These are SEPARATE from invoice trade discounts which are already included in invoice totals
        $discountReceived = $this->calculateDiscountReceived($fromDate, $toDate);
        $discountAllowed = $this->calculateDiscountAllowed($fromDate, $toDate);

        // Income Statement calculations - NEW STRUCTURE WITH COGS
        // Net Sales = Sales - Returns
        $netSales = $totalSales - $salesReturns;

        // Gross Profit = Net Sales - COGS
        $grossProfit = $netSales - $costOfGoodsSold;

        // Operating Expenses = Regular Expenses + Commissions + Discounts Allowed
        $operatingExpenses = $expenses + $commissionsPaid + $discountAllowed;

        // Net Profit = Gross Profit - Operating Expenses + Other Income + Discounts Received
        $netProfit = $grossProfit - $operatingExpenses + $revenues + $discountReceived;

        // Financial Position calculations - CORRECT ACCOUNTING EQUATION
        // Assets = Liabilities + Equity
        $totalAssets = $fixedAssetsValue + $endingInventory + $totalDebtors + $totalCash;
        $shareholderDrawings = $this->calculateShareholderDrawings();

        // Equity = Capital + Net Profit - Drawings
        $equity = $shareholderCapital + $netProfit - $shareholderDrawings;

        // Liabilities = Creditors (amounts owed to suppliers)
        $totalLiabilities = $totalCreditors;

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'shareholder_capital' => $shareholderCapital,
            'shareholder_drawings' => $shareholderDrawings,
            'equity' => $equity,
            'fixed_assets_value' => $fixedAssetsValue,
            'beginning_inventory' => $beginningInventory,
            'ending_inventory' => $endingInventory,
            'total_debtors' => $totalDebtors,
            'total_creditors' => $totalCreditors,
            'total_cash' => $totalCash,
            'total_sales' => $totalSales,
            'sales_returns' => $salesReturns,
            'net_sales' => $netSales,
            'cost_of_goods_sold' => $costOfGoodsSold,
            'gross_profit' => $grossProfit,
            'expenses' => $expenses,
            'commissions_paid' => $commissionsPaid,
            'operating_expenses' => $operatingExpenses,
            'revenues' => $revenues,
            'discount_received' => $discountReceived,
            'discount_allowed' => $discountAllowed,
            'net_profit' => $netProfit,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
        ];
    }

    /**
     * Calculate total fixed assets value from FixedAsset table (book value)
     */
    protected function calculateFixedAssetsValue(): float
    {
        // Book Value = Purchase Amount - Accumulated Depreciation
        $assets = \App\Models\FixedAsset::all();
        $totalBookValue = 0;

        foreach ($assets as $asset) {
            if (method_exists($asset, 'getBookValue')) {
                $totalBookValue += $asset->getBookValue();
            } else {
                $totalBookValue += $asset->purchase_amount - ($asset->accumulated_depreciation ?? 0);
            }
        }

        return (float) $totalBookValue;
    }

    /**
     * Calculate inventory value at a specific date
     *
     * @param string $date The date to calculate inventory up to
     * @param bool $exclusive If true, use < instead of <= for date comparison
     */
    protected function calculateInventoryValue(string $date, bool $exclusive = false): float
    {
        $dateOperator = $exclusive ? '<' : '<=';

        // Use a single optimized query with aggregation instead of loading all products
        $totalValue = DB::table('products')
            ->join('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->where('stock_movements.created_at', $dateOperator, $date)
            ->whereNull('stock_movements.deleted_at') // Exclude soft-deleted movements
            ->selectRaw('products.id, products.avg_cost, SUM(stock_movements.quantity) as total_qty')
            ->where('products.avg_cost', '>', 0)
            ->groupBy('products.id', 'products.avg_cost')
            ->havingRaw('SUM(stock_movements.quantity) > 0')
            ->get()
            ->sum(function($row) {
                return $row->avg_cost * $row->total_qty;
            });

        return (float) $totalValue;
    }

    /**
     * Calculate total debtors (ALL non-shareholder partners with positive balances - they owe us)
     * This is an ASSET regardless of partner type.
     * Examples:
     * - Customer bought on credit (positive balance)
     * - Supplier we returned goods to on credit (positive balance)
     * NOTE: Excludes shareholders as their capital is tracked separately
     */
    protected function calculateTotalDebtors(): float
    {
        return Partner::where('current_balance', '>', 0)
            ->where('type', '!=', 'shareholder')
            ->sum('current_balance');
    }

    /**
     * Calculate total creditors (ALL non-shareholder partners with negative balances - we owe them)
     * This is a LIABILITY regardless of partner type.
     * Returns absolute value since we store supplier debt as negative.
     * Examples:
     * - Supplier we bought from on credit (negative balance)
     * - Customer who overpaid or returned goods on credit (negative balance)
     * NOTE: Excludes shareholders as their capital/drawings are tracked separately
     */
    protected function calculateTotalCreditors(): float
    {
        return abs(Partner::where('current_balance', '<', 0)
            ->where('type', '!=', 'shareholder')
            ->sum('current_balance'));
    }

    /**
     * Calculate total cash from all treasuries
     */
    protected function calculateTotalCash(): float
    {
        // Use single aggregated query instead of looping through treasuries
        return (float) TreasuryTransaction::sum('amount') ?? 0;
    }

    /**
     * Calculate total sales in date range
     */
    protected function calculateTotalSales($fromDate, $toDate): float
    {
        return SalesInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('total');
    }

    /**
     * Calculate COGS (Cost of Goods Sold) from posted sales invoices
     */
    protected function calculateCOGS($fromDate, $toDate): float
    {
        return SalesInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('cost_total');
    }

    /**
     * Calculate total commissions paid to salespeople
     */
    protected function calculateCommissionsPaid($fromDate, $toDate): float
    {
        return SalesInvoice::where('status', 'posted')
            ->where('commission_paid', true)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('commission_amount');
    }

    /**
     * Calculate total purchases in date range
     */
    protected function calculateTotalPurchases($fromDate, $toDate): float
    {
        return PurchaseInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('total');
    }

    /**
     * Calculate sales returns in date range
     */
    protected function calculateSalesReturns($fromDate, $toDate): float
    {
        return SalesReturn::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('total');
    }

    /**
     * Calculate purchase returns in date range
     */
    protected function calculatePurchaseReturns($fromDate, $toDate): float
    {
        return PurchaseReturn::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('total');
    }

    /**
     * Calculate expenses in date range
     */
    protected function calculateExpenses($fromDate, $toDate): float
    {
        return Expense::whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->sum('amount');
    }

    /**
     * Calculate revenues in date range
     */
    protected function calculateRevenues($fromDate, $toDate): float
    {
        return Revenue::whereDate('revenue_date', '>=', $fromDate)
            ->whereDate('revenue_date', '<=', $toDate)
            ->sum('amount');
    }

    /**
     * Calculate total sales discounts given in date range
     */
    protected function calculateSalesDiscounts($fromDate, $toDate): float
    {
        // Calculate fixed header discounts
        $fixedDiscounts = SalesInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->where('discount_type', 'fixed')
            ->sum('discount_value');

        // Calculate percentage header discounts using database-side calculation
        $percentageDiscounts = SalesInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->where('discount_type', 'percentage')
            ->selectRaw('SUM(subtotal * (discount_value / 100)) as total_discount')
            ->value('total_discount');

        // Get item-level discounts
        $itemDiscounts = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->where('sales_invoices.status', 'posted')
            ->whereDate('sales_invoices.created_at', '>=', $fromDate)
            ->whereDate('sales_invoices.created_at', '<=', $toDate)
            ->sum('sales_invoice_items.discount');

        return floatval($fixedDiscounts) + floatval($percentageDiscounts ?? 0) + floatval($itemDiscounts);
    }

    /**
     * Calculate total purchase discounts received in date range
     */
    protected function calculatePurchaseDiscounts($fromDate, $toDate): float
    {
        // Calculate fixed header discounts
        $fixedDiscounts = PurchaseInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->where('discount_type', 'fixed')
            ->sum('discount_value');

        // Calculate percentage header discounts using database-side calculation
        $percentageDiscounts = PurchaseInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->where('discount_type', 'percentage')
            ->selectRaw('SUM(subtotal * (discount_value / 100)) as total_discount')
            ->value('total_discount');

        // Get item-level discounts
        $itemDiscounts = DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->where('purchase_invoices.status', 'posted')
            ->whereDate('purchase_invoices.created_at', '>=', $fromDate)
            ->whereDate('purchase_invoices.created_at', '<=', $toDate)
            ->sum('purchase_invoice_items.discount');

        return floatval($fixedDiscounts) + floatval($percentageDiscounts ?? 0) + floatval($itemDiscounts);
    }

    /**
     * Calculate total shareholder capital
     * Uses current_capital from Partner model which includes:
     * - Initial capital deposits
     * - Asset contributions
     * - Profit allocations
     * - Minus drawings
     */
    protected function calculateShareholderCapital(): float
    {
        return Partner::where('type', 'shareholder')
            ->sum('current_capital');
    }

    /**
     * Calculate total shareholder drawings from partner_drawing transactions
     */
    protected function calculateShareholderDrawings(): float
    {
        return abs(TreasuryTransaction::where('type', 'partner_drawing')
            ->sum('amount'));
    }

    /**
     * Calculate discount received (revenue) - discounts we received from suppliers
     * This is from payments we made to suppliers (Purchase Invoices)
     */
    protected function calculateDiscountReceived($fromDate, $toDate): float
    {
        return \App\Models\InvoicePayment::whereHasMorph(
            'payable',
            [\App\Models\PurchaseInvoice::class]
        )
        ->whereDate('payment_date', '>=', $fromDate)
        ->whereDate('payment_date', '<=', $toDate)
        ->sum('discount');
    }

    /**
     * Calculate discount allowed (expense) - discounts we gave to customers
     * This is from collections we received from customers (Sales Invoices)
     */
    protected function calculateDiscountAllowed($fromDate, $toDate): float
    {
        return \App\Models\InvoicePayment::whereHasMorph(
            'payable',
            [\App\Models\SalesInvoice::class]
        )
        ->whereDate('payment_date', '>=', $fromDate)
        ->whereDate('payment_date', '<=', $toDate)
        ->sum('discount');
    }
}

