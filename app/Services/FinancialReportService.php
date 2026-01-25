<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Partner;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReturn;
use App\Models\Revenue;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
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

        // Trading calculations
        $totalPurchases = $this->calculateTotalPurchases($fromDate, $toDate);
        $purchaseReturns = $this->calculatePurchaseReturns($fromDate, $toDate);
        $salesDiscounts = $this->calculateSalesDiscounts($fromDate, $toDate);
        $purchaseDiscounts = $this->calculatePurchaseDiscounts($fromDate, $toDate);

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
            'sales_discounts' => $salesDiscounts,
            'purchase_discounts' => $purchaseDiscounts,
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
     * Calculate inventory value at a specific date using HISTORICAL avg_cost
     *
     * ACCOUNTING PRINCIPLE: Inventory must be valued at the cost that existed at the valuation date,
     * NOT at current cost. This is critical for the Matching Principle and period-over-period comparisons.
     *
     * @param  string  $date  The date to calculate inventory up to
     * @param  bool  $exclusive  If true, use < instead of <= for date comparison
     */
    protected function calculateInventoryValue(string $date, bool $exclusive = false): float
    {
        $dateCondition = $exclusive ? '<' : '<=';
        $dateTime = $date . ($exclusive ? ' 00:00:00' : ' 23:59:59');

        $totalValue = '0';

        // Get all products that have any stock movements
        $productIds = DB::table('stock_movements')
            ->whereNull('deleted_at')
            ->where('created_at', $dateCondition, $dateTime)
            ->distinct()
            ->pluck('product_id');

        foreach ($productIds as $productId) {
            // Get quantity at this date
            $qty = DB::table('stock_movements')
                ->where('product_id', $productId)
                ->whereNull('deleted_at')
                ->where('created_at', $dateCondition, $dateTime)
                ->sum('quantity');

            if ($qty <= 0) {
                continue;
            }

            // Calculate historical weighted average cost at this date
            // Only from purchase and purchase_return movements up to this date
            $historicalAvgCost = $this->calculateHistoricalAvgCost($productId, $dateTime, $dateCondition);

            if (bccomp($historicalAvgCost, '0', 4) > 0) {
                $productValue = bcmul($historicalAvgCost, (string) $qty, 4);
                $totalValue = bcadd($totalValue, $productValue, 4);
            }
        }

        return (float) $totalValue;
    }

    /**
     * Calculate the weighted average cost for a product at a specific historical date
     *
     * @param  string  $productId  The product ID
     * @param  string  $dateTime  The cutoff datetime
     * @param  string  $dateCondition  Either '<' or '<='
     */
    protected function calculateHistoricalAvgCost(string $productId, string $dateTime, string $dateCondition): string
    {
        // Get all purchase and purchase_return movements up to this date
        $movements = DB::table('stock_movements')
            ->where('product_id', $productId)
            ->whereIn('type', ['purchase', 'purchase_return'])
            ->whereNull('deleted_at')
            ->where('created_at', $dateCondition, $dateTime)
            ->where('quantity', '!=', 0)
            ->select('quantity', 'cost_at_time')
            ->get();

        if ($movements->isEmpty()) {
            // Fall back to current avg_cost if no historical purchase data
            $product = Product::find($productId);
            return (string) ($product->avg_cost ?? 0);
        }

        $totalCost = '0';
        $totalQuantity = '0';

        foreach ($movements as $movement) {
            // cost_at_time * quantity (quantity can be negative for returns)
            $movementCost = bcmul((string) $movement->cost_at_time, (string) $movement->quantity, 4);
            $totalCost = bcadd($totalCost, $movementCost, 4);
            $totalQuantity = bcadd($totalQuantity, (string) $movement->quantity, 4);
        }

        if (bccomp($totalQuantity, '0', 4) <= 0) {
            // If all purchased stock was returned, use last known cost
            $product = Product::find($productId);
            return (string) ($product->avg_cost ?? 0);
        }

        return bcdiv($totalCost, $totalQuantity, 4);
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
        return Partner::whereIn('type', ['customer', 'supplier'])
            ->where('current_balance', '>', 0)
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
        return abs(Partner::whereIn('type', ['customer', 'supplier'])
            ->where('current_balance', '<', 0)
            ->sum('current_balance'));
    }

    /**
     * Calculate total cash from all treasuries
     *
     * IMPORTANT: Excludes non-cash transaction types:
     * - 'discount': Settlement discounts don't affect cash
     * - 'depreciation_expense': Depreciation is non-cash (now tracked in Expense model)
     *
     * BLIND-07 FIX: Optionally filter by treasury type ('cash' or 'bank')
     * @param string|null $treasuryType If null, returns total across all treasuries
     */
    protected function calculateTotalCash(?string $treasuryType = null): float
    {
        $query = TreasuryTransaction::whereNotIn('type', [
            'discount',
            'depreciation_expense', // Legacy - should not exist in new data
        ]);

        // BLIND-07 FIX: Filter by treasury type if specified
        if ($treasuryType !== null) {
            $query->whereHas('treasury', function ($q) use ($treasuryType) {
                $q->where('type', $treasuryType);
            });
        }

        return (float) ($query->sum('amount') ?? 0);
    }

    /**
     * Calculate total cash in physical cash boxes only
     */
    protected function calculateCashOnHand(): float
    {
        return $this->calculateTotalCash('cash');
    }

    /**
     * Calculate total cash in bank accounts only
     */
    protected function calculateBankBalance(): float
    {
        return $this->calculateTotalCash('bank');
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
     * Calculate COGS (Cost of Goods Sold) - Gross minus Returned
     */
    protected function calculateCOGS($fromDate, $toDate): float
    {
        // Gross COGS from posted sales invoices
        $grossCOGS = SalesInvoice::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('cost_total');

        // Returned COGS from posted sales returns (Periodicity Principle)
        $returnedCOGS = SalesReturn::where('status', 'posted')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('cost_total');

        // Net COGS
        return floatval(bcsub((string) $grossCOGS, (string) $returnedCOGS, 4));
    }

    /**
     * Calculate total commissions paid to salespeople
     */
    protected function calculateCommissionsPaid($fromDate, $toDate): float
    {
        // Calculate commissions from treasury transactions to accurately reflect the actual payments/reversals in the period
        $payouts = TreasuryTransaction::where('type', 'commission_payout')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('amount'); // This is already negative

        $reversals = TreasuryTransaction::where('type', 'commission_reversal')
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('amount'); // This is positive

        // Returns absolute "expense" value. Since payouts are negative and reversals positive,
        // we take the negative sum to get a positive expense figure.
        return -($payouts + $reversals);
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
