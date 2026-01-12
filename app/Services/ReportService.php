<?php

namespace App\Services;

use App\Models\InvoicePayment;
use App\Models\Partner;
use App\Models\Product;
use App\Models\SalesInvoice;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Generate Partner Statement Report
     *
     * @param string $partnerId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getPartnerStatement(string $partnerId, string $startDate, string $endDate): array
    {
        // Calculate opening balance
        $openingBalance = $this->calculateOpeningBalance($partnerId, $startDate);

        // Fetch transactions in date range
        $invoices = $this->fetchSalesInvoices($partnerId, $startDate, $endDate);
        $payments = $this->fetchInvoicePayments($partnerId, $startDate, $endDate);
        $returns = $this->fetchSalesReturns($partnerId, $startDate, $endDate);

        // Merge and sort transactions
        $transactions = $invoices
            ->concat($payments)
            ->concat($returns)
            ->sortBy('date')
            ->values();

        // Calculate running balance
        $runningBalance = $openingBalance;
        $transactions = $transactions->map(function ($transaction) use (&$runningBalance) {
            $runningBalance += ($transaction['debit'] - $transaction['credit']);
            $transaction['balance'] = $runningBalance;
            return $transaction;
        });

        $closingBalance = $runningBalance;

        // Get partner with relationships
        $partner = Partner::find($partnerId);

        return [
            'partner' => $partner,
            'opening_balance' => round($openingBalance, 4),
            'transactions' => $transactions,
            'closing_balance' => round($closingBalance, 4),
            'total_debit' => round($transactions->sum('debit'), 4),
            'total_credit' => round($transactions->sum('credit'), 4),
            'from_date' => $startDate,
            'to_date' => $endDate,
        ];
    }

    /**
     * Calculate Opening Balance for Partner Statement
     *
     * @param string $partnerId
     * @param string $startDate
     * @return float
     */
    protected function calculateOpeningBalance(string $partnerId, string $startDate): float
    {
        // Debit: Posted sales invoices total (created_at < start_date)
        $salesDebit = SalesInvoice::where('partner_id', $partnerId)
            ->where('status', 'posted')
            ->where('created_at', '<', $startDate)
            ->sum('total');

        // Credit: Invoice payments (payment_date < start_date)
        $paymentsCredit = InvoicePayment::where('partner_id', $partnerId)
            ->where('payable_type', 'App\\Models\\SalesInvoice')
            ->where('payment_date', '<', $startDate)
            ->sum(DB::raw('amount + discount'));

        // Credit: Sales returns (created_at < start_date)
        $returnsCredit = SalesReturn::where('partner_id', $partnerId)
            ->where('status', 'posted')
            ->where('created_at', '<', $startDate)
            ->sum('total');

        return $salesDebit - $paymentsCredit - $returnsCredit;
    }

    /**
     * Fetch Sales Invoices for Partner Statement
     *
     * @param string $partnerId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    protected function fetchSalesInvoices(string $partnerId, string $startDate, string $endDate): Collection
    {
        return SalesInvoice::where('partner_id', $partnerId)
            ->where('status', 'posted')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with('warehouse')
            ->get()
            ->map(function ($invoice) {
                return [
                    'date' => $invoice->created_at,
                    'type' => 'invoice',
                    'reference' => $invoice->invoice_number,
                    'description' => 'فاتورة مبيعات',
                    'debit' => (float) $invoice->total,
                    'credit' => 0,
                    'warehouse' => $invoice->warehouse?->name,
                ];
            });
    }

    /**
     * Fetch Invoice Payments for Partner Statement
     *
     * @param string $partnerId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    protected function fetchInvoicePayments(string $partnerId, string $startDate, string $endDate): Collection
    {
        return InvoicePayment::where('partner_id', $partnerId)
            ->where('payable_type', 'App\\Models\\SalesInvoice')
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->with('payable')
            ->get()
            ->map(function ($payment) {
                return [
                    'date' => $payment->payment_date,
                    'type' => 'payment',
                    'reference' => $payment->payable?->invoice_number ?? '-',
                    'description' => 'سداد دفعة',
                    'debit' => 0,
                    'credit' => (float) ($payment->amount + $payment->discount),
                    'notes' => $payment->notes,
                ];
            });
    }

    /**
     * Fetch Sales Returns for Partner Statement
     *
     * @param string $partnerId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    protected function fetchSalesReturns(string $partnerId, string $startDate, string $endDate): Collection
    {
        return SalesReturn::where('partner_id', $partnerId)
            ->where('status', 'posted')
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with('warehouse')
            ->get()
            ->map(function ($return) {
                return [
                    'date' => $return->created_at,
                    'type' => 'return',
                    'reference' => $return->return_number,
                    'description' => 'مرتجع مبيعات',
                    'debit' => 0,
                    'credit' => (float) $return->total,
                    'warehouse' => $return->warehouse?->name,
                ];
            });
    }

    /**
     * Generate Stock Card Report
     *
     * @param string $productId
     * @param string|null $warehouseId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getStockCard(string $productId, ?string $warehouseId, string $startDate, string $endDate): array
    {
        // Calculate opening stock
        $openingStock = $this->calculateOpeningStock($productId, $warehouseId, $startDate);

        // Fetch stock movements in date range
        $movements = $this->fetchStockMovements($productId, $warehouseId, $startDate, $endDate);

        // Calculate running stock
        $runningStock = $openingStock;
        $movements = $movements->map(function ($movement) use (&$runningStock) {
            $runningStock += ($movement['in'] - $movement['out']);
            $movement['balance'] = $runningStock;
            return $movement;
        });

        $closingStock = $runningStock;

        // Get product with relationships
        $product = Product::with(['smallUnit', 'largeUnit', 'category'])->find($productId);

        // Get warehouse if specified
        $warehouse = null;
        if ($warehouseId && $warehouseId !== 'all') {
            $warehouse = Warehouse::find($warehouseId);
        }

        return [
            'product' => $product,
            'warehouse' => $warehouse,
            'opening_stock' => $openingStock,
            'movements' => $movements,
            'closing_stock' => $closingStock,
            'total_in' => $movements->sum('in'),
            'total_out' => $movements->sum('out'),
            'from_date' => $startDate,
            'to_date' => $endDate,
        ];
    }

    /**
     * Calculate Opening Stock for Stock Card
     *
     * @param string $productId
     * @param string|null $warehouseId
     * @param string $startDate
     * @return int
     */
    protected function calculateOpeningStock(string $productId, ?string $warehouseId, string $startDate): int
    {
        $query = StockMovement::where('product_id', $productId)
            ->where('created_at', '<', $startDate);

        if ($warehouseId && $warehouseId !== 'all') {
            $query->where('warehouse_id', $warehouseId);
        }

        return (int) $query->sum('quantity');
    }

    /**
     * Fetch Stock Movements for Stock Card
     *
     * @param string $productId
     * @param string|null $warehouseId
     * @param string $startDate
     * @param string $endDate
     * @return Collection
     */
    protected function fetchStockMovements(string $productId, ?string $warehouseId, string $startDate, string $endDate): Collection
    {
        $query = StockMovement::where('product_id', $productId)
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->with(['warehouse', 'reference']);

        if ($warehouseId && $warehouseId !== 'all') {
            $query->where('warehouse_id', $warehouseId);
        }

        return $query->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($movement) {
                // Determine type label
                $typeLabel = match ($movement->type) {
                    'sale' => 'مبيعات',
                    'purchase' => 'مشتريات',
                    'adjustment_in' => 'جرد وارد',
                    'adjustment_out' => 'جرد صادر',
                    'transfer' => 'تحويل',
                    'sale_return' => 'مرتجع مبيعات',
                    'purchase_return' => 'مرتجع مشتريات',
                    default => $movement->type,
                };

                return [
                    'date' => $movement->created_at,
                    'type' => $typeLabel,
                    'reference' => $this->getMovementReferenceNumber($movement),
                    'warehouse' => $movement->warehouse?->name ?? '-',
                    'in' => $movement->quantity > 0 ? $movement->quantity : 0,
                    'out' => $movement->quantity < 0 ? abs($movement->quantity) : 0,
                    'cost' => (float) $movement->cost_at_time,
                ];
            });
    }

    /**
     * Extract Reference Number from Stock Movement
     *
     * @param StockMovement $movement
     * @return string
     */
    protected function getMovementReferenceNumber(StockMovement $movement): string
    {
        if (!$movement->reference) {
            return '-';
        }

        // Handle both snake_case and full class names for reference_type
        return match ($movement->reference_type) {
            'sales_invoice', 'App\\Models\\SalesInvoice' => $movement->reference->invoice_number ?? '-',
            'sales_return', 'App\\Models\\SalesReturn' => $movement->reference->return_number ?? '-',
            'purchase_invoice', 'App\\Models\\PurchaseInvoice' => $movement->reference->invoice_number ?? '-',
            'purchase_return', 'App\\Models\\PurchaseReturn' => $movement->reference->return_number ?? '-',
            'stock_adjustment', 'App\\Models\\StockAdjustment' => $movement->reference->adjustment_number ?? '-',
            'warehouse_transfer', 'App\\Models\\WarehouseTransfer' => $movement->reference->transfer_number ?? '-',
            default => '-',
        };
    }
}
