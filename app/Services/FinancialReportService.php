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

        // Dual calculation for verification during transition
        $fixedAssetsValueOld = (float) GeneralSetting::getValue('fixed_assets_value', '0');
        $fixedAssetsValueNew = $this->calculateFixedAssetsValue();

        // Log discrepancies
        if (abs($fixedAssetsValueOld - $fixedAssetsValueNew) > 0.01) {
            \Log::warning('Fixed Assets Value Mismatch', [
                'old_method' => $fixedAssetsValueOld,
                'new_method' => $fixedAssetsValueNew,
                'difference' => $fixedAssetsValueNew - $fixedAssetsValueOld,
            ]);
        }

        // Use new calculation
        $fixedAssetsValue = $fixedAssetsValueNew;

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

        // Settlement discount calculations (payment-time discounts)
        // These are SEPARATE from invoice trade discounts which are already included in invoice totals
        $discountReceived = $this->calculateDiscountReceived($fromDate, $toDate);
        $discountAllowed = $this->calculateDiscountAllowed($fromDate, $toDate);

        // Income Statement calculations
        // NOTE: Trade discounts are already deducted in invoice totals (total = subtotal - discount)
        // We only add settlement discounts (payment-time discounts) as separate line items
        $debitTotal = $beginningInventory + $totalPurchases + $salesReturns + $expenses + $discountAllowed;
        $creditTotal = $endingInventory + $totalSales + $purchaseReturns + $revenues + $discountReceived;
        $netProfit = $creditTotal - $debitTotal;

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
            'total_purchases' => $totalPurchases,
            'sales_returns' => $salesReturns,
            'purchase_returns' => $purchaseReturns,
            // Trade discounts removed - already included in invoice totals
            'discount_received' => $discountReceived,
            'discount_allowed' => $discountAllowed,
            'expenses' => $expenses,
            'revenues' => $revenues,
            'net_profit' => $netProfit,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
        ];
    }

    /**
     * Calculate total fixed assets value from FixedAsset table
     */
    protected function calculateFixedAssetsValue(): float
    {
        return (float) \App\Models\FixedAsset::sum('purchase_amount') ?? 0;
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

    /**
     * Calculate total sales discounts given in date range
     */
    protected function calculateSalesDiscounts($fromDate, $toDate): float
    {
        // Calculate fixed header discounts
        $fixedDiscounts = SalesInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('discount_type', 'fixed')
            ->sum('discount_value');

        // Calculate percentage header discounts using database-side calculation
        $percentageDiscounts = SalesInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('discount_type', 'percentage')
            ->selectRaw('SUM(subtotal * (discount_value / 100)) as total_discount')
            ->value('total_discount');

        // Get item-level discounts
        $itemDiscounts = DB::table('sales_invoice_items')
            ->join('sales_invoices', 'sales_invoice_items.sales_invoice_id', '=', 'sales_invoices.id')
            ->where('sales_invoices.status', 'posted')
            ->whereBetween('sales_invoices.created_at', [$fromDate, $toDate])
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
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('discount_type', 'fixed')
            ->sum('discount_value');

        // Calculate percentage header discounts using database-side calculation
        $percentageDiscounts = PurchaseInvoice::where('status', 'posted')
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->where('discount_type', 'percentage')
            ->selectRaw('SUM(subtotal * (discount_value / 100)) as total_discount')
            ->value('total_discount');

        // Get item-level discounts
        $itemDiscounts = DB::table('purchase_invoice_items')
            ->join('purchase_invoices', 'purchase_invoice_items.purchase_invoice_id', '=', 'purchase_invoices.id')
            ->where('purchase_invoices.status', 'posted')
            ->whereBetween('purchase_invoices.created_at', [$fromDate, $toDate])
            ->sum('purchase_invoice_items.discount');

        return floatval($fixedDiscounts) + floatval($percentageDiscounts ?? 0) + floatval($itemDiscounts);
    }

    /**
     * Calculate total shareholder capital from capital_deposit transactions
     */
    protected function calculateShareholderCapital(): float
    {
        return TreasuryTransaction::where('type', 'capital_deposit')
            ->sum('amount');
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
        ->whereBetween('payment_date', [$fromDate, $toDate])
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
        ->whereBetween('payment_date', [$fromDate, $toDate])
        ->sum('discount');
    }
}

