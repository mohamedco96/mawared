<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register MorphMap for clean polymorphism
        Relation::enforceMorphMap([
            'sales_invoice' => \App\Models\SalesInvoice::class,
            'purchase_invoice' => \App\Models\PurchaseInvoice::class,
            'sales_return' => \App\Models\SalesReturn::class,
            'purchase_return' => \App\Models\PurchaseReturn::class,
            'warehouse_transfer' => \App\Models\WarehouseTransfer::class,
            'stock_adjustment' => \App\Models\StockAdjustment::class,
            'expense' => \App\Models\Expense::class,
            'revenue' => \App\Models\Revenue::class,
            'financial_transaction' => \App\Models\TreasuryTransaction::class, // Self-reference for standalone transactions
        ]);
    }
}
