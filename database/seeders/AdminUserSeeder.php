<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Admin User Seeder for Al-Rehab (Osool) ERP System
 *
 * Creates the core team members with their specific roles.
 * All emails use @osoolerp.com domain.
 *
 * Team Structure:
 * - Mohamed Ibrahim: Super Admin (Full Access)
 * - Ashraf Al-Ashry: Warehouse Manager (Purchasing & Inventory)
 * - Mahmoud Ashraf: Sales Representative (Sales & Customers)
 * - Rehab Ashraf: Marketing Specialist (Marketing & Analytics)
 *
 * Usage: php artisan db:seed --class=AdminUserSeeder
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "👤 Seeding Al-Rehab team members...\n";

        $users = [
            [
                'name' => 'Mohamed Ibrahim',
                'email' => 'mohamed@osoolerp.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29001011234567',
                'salary_type' => 'monthly',
                'salary_amount' => 0,
                'advance_balance' => 0,
                'role' => 'super_admin',
                'display_name' => 'Mohamed Ibrahim',
            ],
            [
                'name' => 'Ashraf Al-Ashry',
                'email' => 'ashraf@osoolerp.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '28505011234568',
                'salary_type' => 'monthly',
                'salary_amount' => 5000,
                'advance_balance' => 0,
                'role' => 'warehouse_manager',
                'display_name' => 'أشرف العشري',
            ],
            [
                'name' => 'Mahmoud Ashraf',
                'email' => 'mahmoud@osoolerp.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29203011234569',
                'salary_type' => 'daily',
                'salary_amount' => 250,
                'advance_balance' => 0,
                'role' => 'sales_representative',
                'display_name' => 'محمود أشرف',
            ],
            [
                'name' => 'Rehab Ashraf',
                'email' => 'rehab@osoolerp.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29405011234570',
                'salary_type' => 'monthly',
                'salary_amount' => 0,
                'advance_balance' => 0,
                'role' => 'marketing_specialist',
                'display_name' => 'رحاب أشرف (تسويق ومبيعات)',
            ],
        ];

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($users as $userData) {
            $displayName = $userData['display_name'];
            $role = $userData['role'];
            unset($userData['role'], $userData['display_name']);

            $existingUser = User::where('email', $userData['email'])->first();

            if (!$existingUser) {
                // Create new user
                $user = User::create($userData);

                // Assign role if it exists
                $roleModel = Role::where('name', $role)->first();
                if ($roleModel) {
                    $user->assignRole($role);
                    echo "   ✓ Created: {$displayName} → {$role}\n";
                } else {
                    echo "   ⚠️  Created user but role '{$role}' not found: {$displayName}\n";
                }

                $createdCount++;
            } else {
                // Update existing user's role
                $roleModel = Role::where('name', $role)->first();

                if ($roleModel) {
                    if (!$existingUser->hasRole($role)) {
                        $existingUser->syncRoles([$role]);
                        echo "   ✓ Updated role: {$displayName} → {$role}\n";
                        $updatedCount++;
                    } else {
                        echo "   ℹ️  Already exists: {$displayName}\n";
                    }
                }
            }
        }

        if ($createdCount > 0) {
            echo "   ✅ Created {$createdCount} new team members\n";
        }
        if ($updatedCount > 0) {
            echo "   ✅ Updated {$updatedCount} team members\n";
        }
        if ($createdCount === 0 && $updatedCount === 0) {
            echo "   ℹ️  All team members already exist with correct roles\n";
        }

        // ==================================================
        // CRITICAL VERIFICATION: Ensure super_admin has all permissions
        // ==================================================
        $superAdminUser = User::where('email', 'mohamed@osoolerp.com')->first();

        if ($superAdminUser) {
            $superAdminRole = Role::where('name', 'super_admin')->first();

            if ($superAdminRole) {
                // Ensure user has the role
                if (!$superAdminUser->hasRole('super_admin')) {
                    $superAdminUser->assignRole('super_admin');
                    echo "   🔧 Assigned super_admin role to mohamed@osoolerp.com\n";
                }

                // Count permissions
                $totalPermissions = \Spatie\Permission\Models\Permission::count();
                $rolePermissions = $superAdminRole->permissions()->count();
                $userPermissions = $superAdminUser->getAllPermissions()->count();

                echo "\n   🔍 SUPER ADMIN VERIFICATION:\n";
                echo "   ├─ User: mohamed@osoolerp.com\n";
                echo "   ├─ Role: super_admin ✅\n";
                echo "   ├─ Total System Permissions: {$totalPermissions}\n";
                echo "   ├─ Role Permissions: {$rolePermissions}\n";
                echo "   └─ User Effective Permissions: {$userPermissions}\n";

                if ($userPermissions === $totalPermissions) {
                    echo "   ✅ VERIFIED: Super Admin has ALL permissions!\n\n";
                } else {
                    echo "   ⚠️  WARNING: Super Admin missing permissions!\n\n";
                }
            } else {
                echo "   ⚠️  WARNING: super_admin role not found!\n\n";
            }
        } else {
            echo "   ⚠️  WARNING: mohamed@osoolerp.com user not found!\n\n";
        }

        echo "\n   🔑 Default Login Credentials:\n";
        echo "   Email: mohamed@osoolerp.com\n";
        echo "   Password: password\n";
        echo "   ⚠️  Please change passwords after first login!\n\n";
    }
}
