<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration:
     * 1. Calculates cost_total for each posted SalesReturn from its stock movements
     * 2. Restores original SalesInvoice.cost_total values from their stock movements
     *
     * This fixes the Periodicity Principle violation where returns were modifying
     * original invoice cost_total instead of storing cost on the return itself.
     */
    public function up(): void
    {
        // Step 1: For each posted SalesReturn, calculate cost_total from its stock movements
        $returns = DB::table('sales_returns')
            ->where('status', 'posted')
            ->get();

        foreach ($returns as $return) {
            $costTotal = DB::table('stock_movements')
                ->where('reference_type', 'sales_return')
                ->where('reference_id', $return->id)
                ->selectRaw('SUM(ABS(quantity) * cost_at_time) as total')
                ->value('total') ?? 0;

            DB::table('sales_returns')
                ->where('id', $return->id)
                ->update(['cost_total' => $costTotal]);
        }

        // Step 2: Restore original invoice cost_total values from their stock movements
        // This undoes the damage where postSalesReturn was modifying original invoices
        $affectedInvoiceIds = DB::table('sales_returns')
            ->where('status', 'posted')
            ->whereNotNull('sales_invoice_id')
            ->pluck('sales_invoice_id')
            ->unique();

        foreach ($affectedInvoiceIds as $invoiceId) {
            $originalCOGS = DB::table('stock_movements')
                ->where('reference_type', 'sales_invoice')
                ->where('reference_id', $invoiceId)
                ->where('type', 'sale')
                ->selectRaw('SUM(ABS(quantity) * cost_at_time) as total')
                ->value('total') ?? 0;

            DB::table('sales_invoices')
                ->where('id', $invoiceId)
                ->update(['cost_total' => $originalCOGS]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: This is a data migration. Reverting would require re-applying
     * the old (incorrect) logic which we don't want to do.
     */
    public function down(): void
    {
        // Intentionally left empty - we don't want to restore the broken behavior
    }
};
