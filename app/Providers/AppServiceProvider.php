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
        // أضف هذا السطر لإجبار الروابط على أن تكون آمنة
        if (app()->environment('local') || app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

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
            'fixed_asset' => \App\Models\FixedAsset::class,
            'initial_capital' => \App\Models\TreasuryTransaction::class, // Initial capital transactions
            'shareholder_capital' => \App\Models\TreasuryTransaction::class, // Shareholder capital transactions
            'financial_transaction' => \App\Models\TreasuryTransaction::class, // Self-reference for standalone transactions
            'user' => \App\Models\User::class,
            'product' => \App\Models\Product::class,
            'product_category' => \App\Models\ProductCategory::class,
            'partner' => \App\Models\Partner::class,
            'stock_movement' => \App\Models\StockMovement::class,
            'treasury_transaction' => \App\Models\TreasuryTransaction::class,
            'quotation' => \App\Models\Quotation::class,
            'installment' => \App\Models\Installment::class,
        ]);
    }
}
