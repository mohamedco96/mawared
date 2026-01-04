<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Custom permission for viewing profit data
        Permission::create([
            'name' => 'view_profit',
            'guard_name' => 'web'
        ]);

        // Page permission for profitability report
        Permission::create([
            'name' => 'page_ItemProfitabilityReport',
            'guard_name' => 'web'
        ]);

        // Widget permission
        Permission::create([
            'name' => 'widget_BestSellersWidget',
            'guard_name' => 'web'
        ]);

        // Assign to Super Admin role
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo([
                'view_profit',
                'page_ItemProfitabilityReport',
                'widget_BestSellersWidget'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'view_profit')->delete();
        Permission::where('name', 'page_ItemProfitabilityReport')->delete();
        Permission::where('name', 'widget_BestSellersWidget')->delete();
    }
};
