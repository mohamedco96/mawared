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
        // Permission for viewing financial dashboard
        Permission::create([
            'name' => 'view_financial_dashboard',
            'guard_name' => 'web'
        ]);

        // Widget permissions for new widgets
        Permission::create([
            'name' => 'widget_InventoryValueWidget',
            'guard_name' => 'web'
        ]);

        Permission::create([
            'name' => 'widget_OperationalStatsWidget',
            'guard_name' => 'web'
        ]);

        // Assign to Super Admin role
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo([
                'view_financial_dashboard',
                'widget_InventoryValueWidget',
                'widget_OperationalStatsWidget',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Permission::where('name', 'view_financial_dashboard')->delete();
        Permission::where('name', 'widget_InventoryValueWidget')->delete();
        Permission::where('name', 'widget_OperationalStatsWidget')->delete();
    }
};
