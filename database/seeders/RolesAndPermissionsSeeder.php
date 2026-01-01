<?php

namespace Database\Seeders;

use App\Models\User;
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

        // ==================================================
        // METHOD 1: Create Permissions Manually
        // ==================================================

        // Create individual permissions
        Permission::create(['name' => 'view_products']);
        Permission::create(['name' => 'create_products']);
        Permission::create(['name' => 'edit_products']);
        Permission::create(['name' => 'delete_products']);

        // Create multiple permissions at once
        $salesPermissions = ['view_sales', 'create_sales', 'edit_sales', 'delete_sales'];
        foreach ($salesPermissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // ==================================================
        // METHOD 2: Create Roles Manually
        // ==================================================

        // Create a simple role
        $adminRole = Role::create(['name' => 'admin']);

        // Create role with guard (if using multiple guards)
        $managerRole = Role::create(['name' => 'manager', 'guard_name' => 'web']);

        // Create multiple roles
        $roles = ['accountant', 'warehouse_keeper', 'sales_person'];
        foreach ($roles as $roleName) {
            Role::create(['name' => $roleName]);
        }

        // ==================================================
        // METHOD 3: Assign Permissions to Roles
        // ==================================================

        // Assign single permission to role
        $adminRole->givePermissionTo('view_products');

        // Assign multiple permissions to role
        $adminRole->givePermissionTo(['create_products', 'edit_products', 'delete_products']);

        // Assign all permissions to a role (super admin)
        $superAdminRole = Role::create(['name' => 'super_admin']);
        $superAdminRole->givePermissionTo(Permission::all());

        // Or use syncPermissions (replaces all existing permissions)
        $managerRole->syncPermissions(['view_products', 'view_sales', 'create_sales']);

        // ==================================================
        // METHOD 4: Assign Roles to Users
        // ==================================================

        // Find user by email
        $user = User::where('email', 'admin@test.com')->first();

        if ($user) {
            // Assign single role
            $user->assignRole('super_admin');

            // Assign multiple roles
            // $user->assignRole(['admin', 'manager']);

            // Sync roles (replaces all existing roles)
            // $user->syncRoles(['super_admin']);

            // Remove role
            // $user->removeRole('admin');
        }

        // Create user with role
        $newUser = User::create([
            'name' => 'محاسب',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);
        $newUser->assignRole('accountant');

        // ==================================================
        // METHOD 5: Assign Direct Permissions to Users
        // ==================================================

        // Give permission directly to user (without role)
        // $user->givePermissionTo('view_products');

        // Give multiple permissions
        // $user->givePermissionTo(['view_products', 'create_products']);

        // Revoke permission
        // $user->revokePermissionTo('delete_products');

        // ==================================================
        // METHOD 6: Create Role with Specific Permissions
        // ==================================================

        $accountantRole = Role::create(['name' => 'accountant_role']);
        $accountantPermissions = [
            'view_expense',
            'create_expense',
            'edit_expense',
            'view_revenue',
            'create_revenue',
            'edit_revenue',
            'view_treasury',
            'view_any_treasury',
        ];

        foreach ($accountantPermissions as $permission) {
            // Check if permission exists first
            $perm = Permission::firstOrCreate(['name' => $permission]);
            $accountantRole->givePermissionTo($perm);
        }

        // ==================================================
        // EXAMPLE: Complete Role Setup for Your System
        // ==================================================

        // Sales Role
        $salesRole = Role::create(['name' => 'sales_representative']);
        $salesRole->givePermissionTo([
            'view_any_sales::invoice',
            'view_sales::invoice',
            'create_sales::invoice',
            'update_sales::invoice',
            'view_any_quotation',
            'create_quotation',
            'view_any_partner',
            'view_partner',
            'view_any_product',
            'view_product',
        ]);

        // Warehouse Role
        $warehouseRole = Role::create(['name' => 'warehouse_manager']);
        $warehouseRole->givePermissionTo([
            'view_any_warehouse',
            'view_warehouse',
            'create_warehouse',
            'update_warehouse',
            'view_any_warehouse::transfer',
            'create_warehouse::transfer',
            'view_any_stock::adjustment',
            'create_stock::adjustment',
            'view_any_stock::movement',
            'view_stock::movement',
        ]);
    }
}
