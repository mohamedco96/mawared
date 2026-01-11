<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Team Setup Seeder for Al-Rehab ERP System
 *
 * Creates the initial team structure with 4 roles and specific users.
 * Uses @osoolerp.com email domain for all users.
 *
 * Usage: php artisan db:seed --class=TeamSetupSeeder
 */
class TeamSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        echo "\n";
        echo str_repeat("=", 80) . "\n";
        echo "🏢 AL-REHAB ERP - TEAM SETUP SEEDER\n";
        echo str_repeat("=", 80) . "\n\n";

        // ==================================================
        // STEP 1: Create All Permissions
        // ==================================================
        echo "📋 Step 1: Creating Permissions...\n";
        $permissions = $this->getAllPermissions();

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission]);
        }

        echo "   ✓ Created/Updated " . count($permissions) . " permissions\n\n";

        // ==================================================
        // STEP 2: Create Roles with Specific Permissions
        // ==================================================
        echo "🔐 Step 2: Creating Roles...\n";

        $this->createSuperAdminRole();
        $this->createWarehouseManagerRole();
        $this->createSalesRepresentativeRole();
        $this->createMarketingSpecialistRole();

        echo "\n";

        // ==================================================
        // STEP 3: Create Team Members
        // ==================================================
        echo "👥 Step 3: Creating Team Members...\n";

        $this->createTeamMembers();

        echo "\n";
        echo str_repeat("=", 80) . "\n";
        echo "✅ TEAM SETUP COMPLETED SUCCESSFULLY!\n";
        echo str_repeat("=", 80) . "\n\n";

        // ==================================================
        // STEP 4: Verification Summary
        // ==================================================
        $this->printSummary();
    }

    /**
     * Create Super Admin Role with ALL permissions
     */
    private function createSuperAdminRole(): void
    {
        $role = Role::updateOrCreate(['name' => 'super_admin']);

        // Give ALL permissions to super_admin
        $allPermissions = Permission::all();
        $role->syncPermissions($allPermissions);

        $count = $role->permissions()->count();
        echo "   ✓ super_admin: {$count} permissions (FULL ACCESS)\n";
    }

    /**
     * Create Warehouse Manager Role
     * Responsible for: Purchasing, Inventory, Stock Management, Suppliers
     */
    private function createWarehouseManagerRole(): void
    {
        $role = Role::updateOrCreate(['name' => 'warehouse_manager']);

        $permissions = [
            // Products - Full Management
            'view_any_product', 'view_product', 'create_product', 'update_product', 'delete_product',
            'view_any_product::category', 'view_product::category', 'create_product::category',
            'update_product::category', 'delete_product::category',
            'view_any_unit', 'view_unit', 'create_unit', 'update_unit', 'delete_unit',

            // Suppliers (Partners) - Full Management
            'view_any_partner', 'view_partner', 'create_partner', 'update_partner', 'delete_partner',

            // Purchase Operations - Full Control
            'view_any_purchase::invoice', 'view_purchase::invoice', 'create_purchase::invoice',
            'update_purchase::invoice', 'delete_purchase::invoice',
            'view_any_purchase::return', 'view_purchase::return', 'create_purchase::return',
            'update_purchase::return', 'delete_purchase::return',

            // Warehouse & Stock - Full Control
            'view_any_warehouse', 'view_warehouse', 'create_warehouse', 'update_warehouse', 'delete_warehouse',
            'view_any_stock::movement', 'view_stock::movement', 'delete_stock::movement',
            'view_any_stock::adjustment', 'view_stock::adjustment', 'create_stock::adjustment',
            'update_stock::adjustment', 'delete_stock::adjustment',
            'view_any_warehouse::transfer', 'view_warehouse::transfer', 'create_warehouse::transfer',
            'update_warehouse::transfer', 'delete_warehouse::transfer',

            // Financial Visibility (Cost Prices)
            'view_cost_price',

            // Pages Access
            'page_StockCard',
            'page_DailyOperations',

            // Widgets
            'widget_OperationsOverviewWidget',
            'widget_LowStockTableWidget',
        ];

        $role->syncPermissions($permissions);
        echo "   ✓ warehouse_manager: " . count($permissions) . " permissions (Purchasing & Inventory)\n";
    }

    /**
     * Create Sales Representative Role
     * Responsible for: Sales, Customers, Quotations, Invoicing
     */
    private function createSalesRepresentativeRole(): void
    {
        $role = Role::updateOrCreate(['name' => 'sales_representative']);

        $permissions = [
            // Sales Operations - Full Control
            'view_any_sales::invoice', 'view_sales::invoice', 'create_sales::invoice',
            'update_sales::invoice', 'delete_sales::invoice',
            'view_any_sales::return', 'view_sales::return', 'create_sales::return',
            'update_sales::return', 'delete_sales::return',

            // Quotations - Full Control
            'view_any_quotation', 'view_quotation', 'create_quotation', 'update_quotation', 'delete_quotation',

            // Customers (Partners) - Full Management
            'view_any_partner', 'view_partner', 'create_partner', 'update_partner', 'delete_partner',

            // Products - View Only (NO cost price visibility)
            'view_any_product', 'view_product',
            'view_any_product::category', 'view_product::category',

            // Installments - Full Control
            'view_any_installment', 'view_installment', 'create_installment',
            'update_installment', 'delete_installment',

            // Payments - View and Create
            'view_any_invoice::payment', 'view_invoice::payment', 'create_invoice::payment',

            // Stock - View Only (to check availability)
            'view_any_stock::movement', 'view_stock::movement',
            'view_any_warehouse', 'view_warehouse',

            // Pages Access
            'page_CollectPayments',
            'page_PartnerStatement',
            'page_DailyOperations',

            // Widgets
            'widget_OperationsOverviewWidget',
            'widget_TopDebtorsTableWidget',
            'widget_TopSellingProductsWidget',
        ];

        $role->syncPermissions($permissions);
        echo "   ✓ sales_representative: " . count($permissions) . " permissions (Sales & Customers)\n";
    }

    /**
     * Create Marketing Specialist Role
     * Responsible for: Sales Analytics, Customer Insights, Marketing Campaigns, Reports
     */
    private function createMarketingSpecialistRole(): void
    {
        $role = Role::updateOrCreate(['name' => 'marketing_specialist']);

        $permissions = [
            // Sales - View Only (for analytics)
            'view_any_sales::invoice', 'view_sales::invoice',
            'view_any_quotation', 'view_quotation',

            // Customers - View and Create (for campaigns)
            'view_any_partner', 'view_partner', 'create_partner', 'update_partner',

            // Products - View Only
            'view_any_product', 'view_product',
            'view_any_product::category', 'view_product::category',

            // Reports & Analytics - Full Access
            'page_ItemProfitabilityReport',
            'page_ProfitLossReport',
            'page_PartnerStatement',

            // Financial Visibility (Profit Analysis)
            'view_profit',

            // Widgets - Dashboard Access
            'widget_FinancialOverviewWidget',
            'widget_OperationsOverviewWidget',
            'widget_TopDebtorsTableWidget',
            'widget_TopSellingProductsWidget',
            'widget_CashFlowChartWidget',

            // Activity Logs (to track customer interactions)
            'view_any_activity::log', 'view_activity::log',
        ];

        $role->syncPermissions($permissions);
        echo "   ✓ marketing_specialist: " . count($permissions) . " permissions (Marketing & Analytics)\n";
    }

    /**
     * Create Team Members with specific roles
     */
    private function createTeamMembers(): void
    {
        $teamMembers = [
            [
                'name' => 'Mohamed Ibrahim',
                'email' => 'mohamed@osoolerp.com',
                'role' => 'super_admin',
                'title' => 'سوبر أدمن',
                'national_id' => '29001011234567',
                'salary_type' => 'monthly',
                'salary_amount' => 15000.00,
            ],
            [
                'name' => 'Ashraf Al-Ashry',
                'name_arabic' => 'أشرف العشري',
                'email' => 'ashraf@osoolerp.com',
                'role' => 'warehouse_manager',
                'title' => 'مسؤول مشتريات ومخازن',
                'national_id' => '28505011234568',
                'salary_type' => 'monthly',
                'salary_amount' => 8000.00,
            ],
            [
                'name' => 'Mahmoud Ashraf',
                'name_arabic' => 'محمود أشرف',
                'email' => 'mahmoud@osoolerp.com',
                'role' => 'sales_representative',
                'title' => 'مسؤول مبيعات ومندوب',
                'national_id' => '29203011234569',
                'salary_type' => 'monthly',
                'salary_amount' => 7000.00,
            ],
            [
                'name' => 'Rehab Ashraf',
                'name_arabic' => 'رحاب أشرف',
                'email' => 'rehab@osoolerp.com',
                'role' => 'marketing_specialist',
                'title' => 'تسويق ومبيعات',
                'national_id' => '29405011234570',
                'salary_type' => 'monthly',
                'salary_amount' => 6500.00,
            ],
        ];

        foreach ($teamMembers as $member) {
            $role = $member['role'];
            unset($member['role'], $member['title']);

            $user = User::updateOrCreate(
                ['email' => $member['email']],
                array_merge($member, [
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'advance_balance' => 0,
                ])
            );

            // Assign role
            $user->syncRoles([$role]);

            $displayName = $member['name_arabic'] ?? $member['name'];
            echo "   ✓ {$displayName} ({$member['email']}) → {$role}\n";
        }
    }

    /**
     * Print verification summary
     */
    private function printSummary(): void
    {
        echo "\n📊 VERIFICATION SUMMARY:\n";
        echo str_repeat("-", 80) . "\n";

        $roles = Role::withCount('permissions', 'users')->get();

        foreach ($roles as $role) {
            echo sprintf(
                "   %s: %d permissions, %d users\n",
                str_pad($role->name, 25),
                $role->permissions_count,
                $role->users_count
            );
        }

        echo str_repeat("-", 80) . "\n";
        echo "   Total Roles: " . $roles->count() . "\n";
        echo "   Total Permissions: " . Permission::count() . "\n";
        echo "   Total Users: " . User::count() . "\n";
        echo str_repeat("-", 80) . "\n\n";

        echo "🔑 DEFAULT CREDENTIALS:\n";
        echo "   Email: mohamed@osoolerp.com (Super Admin)\n";
        echo "   Password: password\n\n";

        echo "   All users have the same default password: password\n";
        echo "   ⚠️  Please change passwords after first login!\n\n";
    }

    /**
     * Get all system permissions
     */
    private function getAllPermissions(): array
    {
        return [
            // Users
            'view_any_user', 'view_user', 'create_user', 'update_user', 'delete_user',
            'restore_user', 'force_delete_user',

            // Roles
            'view_any_role', 'view_role', 'create_role', 'update_role', 'delete_role',

            // Partners (Customers & Suppliers)
            'view_any_partner', 'view_partner', 'create_partner', 'update_partner', 'delete_partner',
            'restore_partner', 'force_delete_partner',

            // Products
            'view_any_product', 'view_product', 'create_product', 'update_product', 'delete_product',
            'restore_product', 'force_delete_product',

            // Product Categories
            'view_any_product::category', 'view_product::category', 'create_product::category',
            'update_product::category', 'delete_product::category',

            // Units
            'view_any_unit', 'view_unit', 'create_unit', 'update_unit', 'delete_unit',

            // Warehouses
            'view_any_warehouse', 'view_warehouse', 'create_warehouse', 'update_warehouse', 'delete_warehouse',

            // Sales Invoices
            'view_any_sales::invoice', 'view_sales::invoice', 'create_sales::invoice',
            'update_sales::invoice', 'delete_sales::invoice', 'restore_sales::invoice',
            'force_delete_sales::invoice',

            // Purchase Invoices
            'view_any_purchase::invoice', 'view_purchase::invoice', 'create_purchase::invoice',
            'update_purchase::invoice', 'delete_purchase::invoice', 'restore_purchase::invoice',
            'force_delete_purchase::invoice',

            // Sales Returns
            'view_any_sales::return', 'view_sales::return', 'create_sales::return',
            'update_sales::return', 'delete_sales::return',

            // Purchase Returns
            'view_any_purchase::return', 'view_purchase::return', 'create_purchase::return',
            'update_purchase::return', 'delete_purchase::return',

            // Quotations
            'view_any_quotation', 'view_quotation', 'create_quotation', 'update_quotation',
            'delete_quotation', 'restore_quotation', 'force_delete_quotation',

            // Installments
            'view_any_installment', 'view_installment', 'create_installment',
            'update_installment', 'delete_installment',

            // Invoice Payments
            'view_any_invoice::payment', 'view_invoice::payment', 'create_invoice::payment',
            'update_invoice::payment', 'delete_invoice::payment',

            // Stock Movements
            'view_any_stock::movement', 'view_stock::movement', 'delete_stock::movement',

            // Stock Adjustments
            'view_any_stock::adjustment', 'view_stock::adjustment', 'create_stock::adjustment',
            'update_stock::adjustment', 'delete_stock::adjustment',

            // Warehouse Transfers
            'view_any_warehouse::transfer', 'view_warehouse::transfer', 'create_warehouse::transfer',
            'update_warehouse::transfer', 'delete_warehouse::transfer',

            // Treasuries
            'view_any_treasury', 'view_treasury', 'create_treasury', 'update_treasury', 'delete_treasury',

            // Treasury Transactions
            'view_any_treasury::transaction', 'view_treasury::transaction', 'create_treasury::transaction',
            'update_treasury::transaction', 'delete_treasury::transaction',

            // Expenses
            'view_any_expense', 'view_expense', 'create_expense', 'update_expense', 'delete_expense',

            // Revenues
            'view_any_revenue', 'view_revenue', 'create_revenue', 'update_revenue', 'delete_revenue',

            // Fixed Assets
            'view_any_fixed::asset', 'view_fixed::asset', 'create_fixed::asset',
            'update_fixed::asset', 'delete_fixed::asset',

            // Activity Logs
            'view_any_activity::log', 'view_activity::log', 'delete_activity::log',

            // General Settings
            'view_general::setting', 'update_general::setting',

            // Backup Operations
            'download-backup', 'delete-backup', 'restore-backup',

            // Filament Pages
            'page_Backups', 'page_CollectPayments', 'page_DailyOperations', 'page_GeneralSettings',
            'page_ItemProfitabilityReport', 'page_PartnerStatement', 'page_ProfitLossReport',
            'page_StockCard',

            // Filament Widgets
            'widget_FinancialOverviewWidget', 'widget_LatestActivitiesWidget',
            'widget_OperationsOverviewWidget', 'widget_CashFlowChartWidget',
            'widget_LowStockTableWidget', 'widget_TopCreditorsTableWidget',
            'widget_TopDebtorsTableWidget', 'widget_TopSellingProductsWidget',

            // Financial Visibility
            'view_cost_price',  // View purchase costs and supplier prices
            'view_profit',      // View profit margins and profitability
        ];
    }
}
