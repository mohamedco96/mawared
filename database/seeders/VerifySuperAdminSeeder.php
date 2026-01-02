<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Verification Seeder to Check Super Admin Permissions
 * Run this after seeding to verify super_admin has ALL permissions
 *
 * Usage: php artisan db:seed --class=VerifySuperAdminSeeder
 */
class VerifySuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        echo "\n";
        echo str_repeat("=", 70) . "\n";
        echo "🔍 SUPER ADMIN VERIFICATION REPORT\n";
        echo str_repeat("=", 70) . "\n\n";

        // Find super admin user
        $superAdminUser = User::where('email', 'admin@mawared.com')->first();

        if (!$superAdminUser) {
            echo "❌ ERROR: User 'admin@mawared.com' not found!\n";
            echo "   Please run AdminUserSeeder first.\n\n";
            return;
        }

        // Find super admin role
        $superAdminRole = Role::where('name', 'super_admin')->first();

        if (!$superAdminRole) {
            echo "❌ ERROR: Role 'super_admin' not found!\n";
            echo "   Please run RolesAndPermissionsSeeder first.\n\n";
            return;
        }

        // Get all system permissions
        $allPermissions = Permission::all();
        $totalPermissions = $allPermissions->count();

        // Get role permissions
        $rolePermissions = $superAdminRole->permissions;
        $rolePermissionCount = $rolePermissions->count();

        // Get user's effective permissions
        $userPermissions = $superAdminUser->getAllPermissions();
        $userPermissionCount = $userPermissions->count();

        // Check if user has the role
        $hasRole = $superAdminUser->hasRole('super_admin');

        echo "👤 USER INFORMATION:\n";
        echo "   Name: {$superAdminUser->name}\n";
        echo "   Email: {$superAdminUser->email}\n";
        echo "   ID: {$superAdminUser->id}\n\n";

        echo "🔐 ROLE INFORMATION:\n";
        echo "   Has super_admin role: " . ($hasRole ? "✅ YES" : "❌ NO") . "\n";
        if (!$hasRole) {
            echo "   🔧 Fixing: Assigning super_admin role...\n";
            $superAdminUser->assignRole('super_admin');
            echo "   ✅ Role assigned!\n";
        }
        echo "\n";

        echo "📊 PERMISSION STATISTICS:\n";
        echo "   Total System Permissions: {$totalPermissions}\n";
        echo "   super_admin Role Permissions: {$rolePermissionCount}\n";
        echo "   User Effective Permissions: {$userPermissionCount}\n\n";

        // Detailed verification
        echo "🔍 DETAILED VERIFICATION:\n";

        // Check 1: Role has all permissions
        if ($rolePermissionCount === $totalPermissions) {
            echo "   ✅ super_admin ROLE has ALL {$totalPermissions} permissions\n";
        } else {
            echo "   ❌ super_admin ROLE missing permissions!\n";
            echo "      Expected: {$totalPermissions}, Actual: {$rolePermissionCount}\n";

            // Find missing permissions
            $rolePermissionNames = $rolePermissions->pluck('name')->toArray();
            $allPermissionNames = $allPermissions->pluck('name')->toArray();
            $missing = array_diff($allPermissionNames, $rolePermissionNames);

            if (!empty($missing)) {
                echo "      Missing: " . implode(', ', array_slice($missing, 0, 5));
                if (count($missing) > 5) {
                    echo " ... and " . (count($missing) - 5) . " more";
                }
                echo "\n";

                echo "   🔧 Fixing: Syncing all permissions to super_admin role...\n";
                $superAdminRole->syncPermissions($allPermissions);
                echo "   ✅ Permissions synced!\n";
            }
        }

        // Check 2: User has all permissions (through role)
        if ($userPermissionCount === $totalPermissions) {
            echo "   ✅ USER has ALL {$totalPermissions} permissions (via role)\n";
        } else {
            echo "   ❌ USER missing permissions!\n";
            echo "      Expected: {$totalPermissions}, Actual: {$userPermissionCount}\n";
        }

        echo "\n";

        // Final status
        $finalCheck = $superAdminUser->fresh()->getAllPermissions()->count();

        echo str_repeat("=", 70) . "\n";
        if ($finalCheck === $totalPermissions) {
            echo "✅ SUCCESS: admin@mawared.com has ALL {$totalPermissions} permissions!\n";
        } else {
            echo "⚠️  WARNING: admin@mawared.com has {$finalCheck}/{$totalPermissions} permissions\n";
        }
        echo str_repeat("=", 70) . "\n\n";

        // Show some sample permissions the user has
        echo "📋 SAMPLE PERMISSIONS (first 10):\n";
        $samplePermissions = $superAdminUser->getAllPermissions()->take(10);
        foreach ($samplePermissions as $index => $permission) {
            echo "   " . ($index + 1) . ". {$permission->name}\n";
        }

        if ($totalPermissions > 10) {
            echo "   ... and " . ($totalPermissions - 10) . " more permissions\n";
        }

        echo "\n";

        // Test specific critical permissions
        echo "🧪 TESTING CRITICAL PERMISSIONS:\n";
        $criticalPermissions = [
            'view_any_user',
            'create_user',
            'delete_user',
            'view_any_role',
            'create_role',
            'delete_role',
            'create_sales::invoice',
            'delete_sales::invoice',
            'create_purchase::invoice',
            'delete_purchase::invoice',
            'create_treasury::transaction',
            'delete_treasury::transaction',
        ];

        $allCriticalPass = true;
        foreach ($criticalPermissions as $permName) {
            $hasPerm = $superAdminUser->hasPermissionTo($permName);
            $status = $hasPerm ? "✅" : "❌";
            echo "   {$status} {$permName}\n";

            if (!$hasPerm) {
                $allCriticalPass = false;
            }
        }

        echo "\n";

        if ($allCriticalPass) {
            echo "✅ All critical permissions verified!\n\n";
        } else {
            echo "⚠️  Some critical permissions are missing!\n\n";
        }

        // Summary
        echo str_repeat("=", 70) . "\n";
        echo "📝 SUMMARY\n";
        echo str_repeat("=", 70) . "\n";
        echo "User admin@mawared.com is a SUPER ADMIN with:\n";
        echo "• Total Permissions: {$finalCheck} / {$totalPermissions}\n";
        echo "• Role: super_admin " . ($hasRole ? "✅" : "❌") . "\n";
        echo "• Status: " . ($finalCheck === $totalPermissions ? "✅ FULLY AUTHORIZED" : "⚠️  INCOMPLETE") . "\n";
        echo str_repeat("=", 70) . "\n\n";
    }
}
