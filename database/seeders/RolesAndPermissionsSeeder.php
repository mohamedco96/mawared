<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        echo "🔐 Seeding roles and permissions...\n";

        // ==================================================
        // STEP 1: Create Comprehensive Permissions
        // ==================================================

        $permissions = $this->getPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        echo "   ✓ Created " . count($permissions) . " permissions\n";

        // ==================================================
        // STEP 2: Create Roles
        // ==================================================

        // Super Admin - Full Access to EVERYTHING
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);

        // CRITICAL: Give super_admin ALL permissions (no restrictions)
        $allPermissions = Permission::all();
        $superAdmin->syncPermissions($allPermissions);

        // Verify all permissions were assigned
        $assignedCount = $superAdmin->permissions()->count();
        $totalCount = $allPermissions->count();

        if ($assignedCount === $totalCount) {
            echo "   ✓ Created 'super_admin' role with ALL {$totalCount} permissions ✅\n";
        } else {
            echo "   ⚠️  WARNING: super_admin has {$assignedCount}/{$totalCount} permissions\n";
        }

        // Admin - Almost Full Access (except user management)
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions(
            Permission::where('name', 'not like', '%user%')
                ->where('name', 'not like', '%role%')
                ->pluck('name')
        );
        echo "   ✓ Created 'admin' role\n";

        // Manager - Business Operations
        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->syncPermissions([
            // Partners
            'view_any_partner', 'view_partner', 'create_partner', 'update_partner',

            // Products
            'view_any_product', 'view_product', 'create_product', 'update_product',

            // Sales
            'view_any_sales::invoice', 'view_sales::invoice', 'create_sales::invoice', 'update_sales::invoice',
            'view_any_quotation', 'view_quotation', 'create_quotation', 'update_quotation',
            'view_any_sales::return', 'view_sales::return', 'create_sales::return',

            // Purchases
            'view_any_purchase::invoice', 'view_purchase::invoice', 'create_purchase::invoice', 'update_purchase::invoice',
            'view_any_purchase::return', 'view_purchase::return', 'create_purchase::return',

            // Inventory
            'view_any_warehouse', 'view_warehouse',
            'view_any_stock::movement', 'view_stock::movement',
            'view_any_stock::adjustment', 'view_stock::adjustment', 'create_stock::adjustment',
            'view_any_warehouse::transfer', 'view_warehouse::transfer', 'create_warehouse::transfer',

            // Finance (View Only)
            'view_any_treasury', 'view_treasury',
            'view_any_expense', 'view_expense',
            'view_any_revenue', 'view_revenue',
        ]);
        echo "   ✓ Created 'manager' role\n";

        // Accountant - Financial Focus
        $accountant = Role::firstOrCreate(['name' => 'accountant']);
        $accountant->syncPermissions([
            // Treasury
            'view_any_treasury', 'view_treasury', 'create_treasury', 'update_treasury',
            'view_any_treasury::transaction', 'view_treasury::transaction', 'create_treasury::transaction',

            // Expenses & Revenues
            'view_any_expense', 'view_expense', 'create_expense', 'update_expense',
            'view_any_revenue', 'view_revenue', 'create_revenue', 'update_revenue',

            // Invoices (View & Payment)
            'view_any_sales::invoice', 'view_sales::invoice',
            'view_any_purchase::invoice', 'view_purchase::invoice',
            'view_any_invoice::payment', 'view_invoice::payment', 'create_invoice::payment',

            // Partners (View)
            'view_any_partner', 'view_partner',

            // Fixed Assets
            'view_any_fixed::asset', 'view_fixed::asset', 'create_fixed::asset', 'update_fixed::asset',
        ]);
        echo "   ✓ Created 'accountant' role\n";

        // Sales Representative
        $sales = Role::firstOrCreate(['name' => 'sales_representative']);
        $sales->syncPermissions([
            // Sales Operations
            'view_any_sales::invoice', 'view_sales::invoice', 'create_sales::invoice', 'update_sales::invoice',
            'view_any_quotation', 'view_quotation', 'create_quotation', 'update_quotation',
            'view_any_sales::return', 'view_sales::return', 'create_sales::return',

            // Partners (Customers)
            'view_any_partner', 'view_partner', 'create_partner', 'update_partner',

            // Products (View)
            'view_any_product', 'view_product',

            // Installments
            'view_any_installment', 'view_installment', 'create_installment', 'update_installment',
        ]);
        echo "   ✓ Created 'sales_representative' role\n";

        // Warehouse Keeper
        $warehouse = Role::firstOrCreate(['name' => 'warehouse_keeper']);
        $warehouse->syncPermissions([
            // Warehouse Management
            'view_any_warehouse', 'view_warehouse',

            // Stock Operations
            'view_any_stock::movement', 'view_stock::movement',
            'view_any_stock::adjustment', 'view_stock::adjustment', 'create_stock::adjustment', 'update_stock::adjustment',
            'view_any_warehouse::transfer', 'view_warehouse::transfer', 'create_warehouse::transfer', 'update_warehouse::transfer',

            // Products (View & Update)
            'view_any_product', 'view_product', 'update_product',

            // Purchase Invoices (View for receiving)
            'view_any_purchase::invoice', 'view_purchase::invoice',

            // Sales Invoices (View for shipping)
            'view_any_sales::invoice', 'view_sales::invoice',
        ]);
        echo "   ✓ Created 'warehouse_keeper' role\n";

        // Purchasing Agent
        $purchasing = Role::firstOrCreate(['name' => 'purchasing_agent']);
        $purchasing->syncPermissions([
            // Purchase Operations
            'view_any_purchase::invoice', 'view_purchase::invoice', 'create_purchase::invoice', 'update_purchase::invoice',
            'view_any_purchase::return', 'view_purchase::return', 'create_purchase::return',

            // Partners (Suppliers)
            'view_any_partner', 'view_partner', 'create_partner', 'update_partner',

            // Products (View & Update Prices)
            'view_any_product', 'view_product', 'update_product',

            // Stock (View)
            'view_any_stock::movement', 'view_stock::movement',
        ]);
        echo "   ✓ Created 'purchasing_agent' role\n";

        // Viewer (Read-Only)
        $viewer = Role::firstOrCreate(['name' => 'viewer']);
        $viewer->syncPermissions(
            Permission::where('name', 'like', 'view%')->pluck('name')
        );
        echo "   ✓ Created 'viewer' role (read-only)\n";

        echo "   ✅ Roles and permissions seeding completed!\n\n";
    }

    /**
     * Get all system permissions
     */
    private function getPermissions(): array
    {
        return [
            // Users
            'view_any_user',
            'view_user',
            'create_user',
            'update_user',
            'delete_user',
            'restore_user',
            'force_delete_user',

            // Roles
            'view_any_role',
            'view_role',
            'create_role',
            'update_role',
            'delete_role',

            // Partners
            'view_any_partner',
            'view_partner',
            'create_partner',
            'update_partner',
            'delete_partner',
            'restore_partner',
            'force_delete_partner',

            // Products
            'view_any_product',
            'view_product',
            'create_product',
            'update_product',
            'delete_product',
            'restore_product',
            'force_delete_product',

            // Product Categories
            'view_any_product::category',
            'view_product::category',
            'create_product::category',
            'update_product::category',
            'delete_product::category',

            // Units
            'view_any_unit',
            'view_unit',
            'create_unit',
            'update_unit',
            'delete_unit',

            // Warehouses
            'view_any_warehouse',
            'view_warehouse',
            'create_warehouse',
            'update_warehouse',
            'delete_warehouse',

            // Sales Invoices
            'view_any_sales::invoice',
            'view_sales::invoice',
            'create_sales::invoice',
            'update_sales::invoice',
            'delete_sales::invoice',
            'restore_sales::invoice',
            'force_delete_sales::invoice',

            // Purchase Invoices
            'view_any_purchase::invoice',
            'view_purchase::invoice',
            'create_purchase::invoice',
            'update_purchase::invoice',
            'delete_purchase::invoice',
            'restore_purchase::invoice',
            'force_delete_purchase::invoice',

            // Sales Returns
            'view_any_sales::return',
            'view_sales::return',
            'create_sales::return',
            'update_sales::return',
            'delete_sales::return',

            // Purchase Returns
            'view_any_purchase::return',
            'view_purchase::return',
            'create_purchase::return',
            'update_purchase::return',
            'delete_purchase::return',

            // Quotations
            'view_any_quotation',
            'view_quotation',
            'create_quotation',
            'update_quotation',
            'delete_quotation',
            'restore_quotation',
            'force_delete_quotation',

            // Installments
            'view_any_installment',
            'view_installment',
            'create_installment',
            'update_installment',
            'delete_installment',

            // Invoice Payments
            'view_any_invoice::payment',
            'view_invoice::payment',
            'create_invoice::payment',
            'update_invoice::payment',
            'delete_invoice::payment',

            // Stock Movements
            'view_any_stock::movement',
            'view_stock::movement',
            'delete_stock::movement',

            // Stock Adjustments
            'view_any_stock::adjustment',
            'view_stock::adjustment',
            'create_stock::adjustment',
            'update_stock::adjustment',
            'delete_stock::adjustment',

            // Warehouse Transfers
            'view_any_warehouse::transfer',
            'view_warehouse::transfer',
            'create_warehouse::transfer',
            'update_warehouse::transfer',
            'delete_warehouse::transfer',

            // Treasuries
            'view_any_treasury',
            'view_treasury',
            'create_treasury',
            'update_treasury',
            'delete_treasury',

            // Treasury Transactions
            'view_any_treasury::transaction',
            'view_treasury::transaction',
            'create_treasury::transaction',
            'update_treasury::transaction',
            'delete_treasury::transaction',

            // Expenses
            'view_any_expense',
            'view_expense',
            'create_expense',
            'update_expense',
            'delete_expense',

            // Revenues
            'view_any_revenue',
            'view_revenue',
            'create_revenue',
            'update_revenue',
            'delete_revenue',

            // Fixed Assets
            'view_any_fixed::asset',
            'view_fixed::asset',
            'create_fixed::asset',
            'update_fixed::asset',
            'delete_fixed::asset',

            // Activity Logs
            'view_any_activity::log',
            'view_activity::log',
            'delete_activity::log',

            // General Settings
            'view_general::setting',
            'update_general::setting',

            // Backup Operations
            'download-backup',
            'delete-backup',
            'restore-backup',

            // ==================================================
            // FILAMENT PAGES - Explicit Access Permissions
            // ==================================================
            'page_Backups',
            'page_DailyOperations',
            'page_GeneralSettings',
            'page_PartnerStatement',
            'page_ProfitLossReport',
            'page_StockCard',

            // ==================================================
            // FILAMENT WIDGETS - Dashboard Widgets Permissions
            // ==================================================
            'widget_FinancialOverviewWidget',
            'widget_LatestActivitiesWidget',
            'widget_OperationsOverviewWidget',
            'widget_CashFlowChartWidget',
            'widget_LowStockTableWidget',
            'widget_TopCreditorsTableWidget',
            'widget_TopDebtorsTableWidget',
            'widget_TopSellingProductsWidget',
        ];
    }
}
