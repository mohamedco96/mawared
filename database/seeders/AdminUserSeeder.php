<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "👤 Seeding admin users...\n";

        $users = [
            [
                'name' => 'مدير النظام',
                'email' => 'admin@mawared.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29012011234567',
                'salary_type' => 'monthly',
                'salary_amount' => 10000.00,
                'advance_balance' => 0,
                'role' => 'super_admin', // Super Admin with full access
            ],
            [
                'name' => 'محمد سعيد - محاسب',
                'email' => 'accountant@mawared.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29203011234568',
                'salary_type' => 'monthly',
                'salary_amount' => 6000.00,
                'advance_balance' => 0,
                'role' => 'accountant',
            ],
            [
                'name' => 'أحمد عبدالله - مندوب مبيعات',
                'email' => 'sales@mawared.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29405011234569',
                'salary_type' => 'daily',
                'salary_amount' => 200.00,
                'advance_balance' => 0,
                'role' => 'sales_representative',
            ],
            [
                'name' => 'علي حسن - أمين مخزن',
                'email' => 'warehouse@mawared.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29607011234570',
                'salary_type' => 'monthly',
                'salary_amount' => 4500.00,
                'advance_balance' => 500.00,
                'role' => 'warehouse_keeper',
            ],
            [
                'name' => 'فاطمة محمود - مدير عام',
                'email' => 'manager@mawared.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29809011234571',
                'salary_type' => 'monthly',
                'salary_amount' => 8000.00,
                'advance_balance' => 0,
                'role' => 'manager',
            ],
            [
                'name' => 'حسن علي - مسؤول المشتريات',
                'email' => 'purchasing@mawared.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'national_id' => '29010111234572',
                'salary_type' => 'monthly',
                'salary_amount' => 5500.00,
                'advance_balance' => 0,
                'role' => 'purchasing_agent',
            ],
        ];

        $createdCount = 0;

        foreach ($users as $userData) {
            $existingUser = User::where('email', $userData['email'])->first();

            if (!$existingUser) {
                $role = $userData['role'];
                unset($userData['role']); // Remove role from user data

                $user = User::create($userData);

                // Assign role if it exists
                $roleModel = Role::where('name', $role)->first();
                if ($roleModel) {
                    $user->assignRole($role);
                    echo "   ✓ Created user: {$user->email} with role: {$role}\n";
                } else {
                    echo "   ⚠️  Created user: {$user->email} but role '{$role}' not found\n";
                }

                $createdCount++;
            } else {
                // Update role for existing user
                $role = $userData['role'];
                $roleModel = Role::where('name', $role)->first();

                if ($roleModel && !$existingUser->hasRole($role)) {
                    $existingUser->syncRoles([$role]);
                    echo "   ✓ Updated role for existing user: {$existingUser->email} to {$role}\n";
                }
            }
        }

        if ($createdCount > 0) {
            echo "   ✅ Created $createdCount new users\n";
        } else {
            echo "   ℹ️  All users already exist\n";
        }

        // ==================================================
        // CRITICAL VERIFICATION: Ensure super_admin has all permissions
        // ==================================================
        $superAdminUser = User::where('email', 'admin@mawared.com')->first();

        if ($superAdminUser) {
            $superAdminRole = Role::where('name', 'super_admin')->first();

            if ($superAdminRole) {
                // Ensure user has the role
                if (!$superAdminUser->hasRole('super_admin')) {
                    $superAdminUser->assignRole('super_admin');
                    echo "   🔧 Assigned super_admin role to admin@mawared.com\n";
                }

                // Count permissions
                $totalPermissions = \Spatie\Permission\Models\Permission::count();
                $rolePermissions = $superAdminRole->permissions()->count();
                $userPermissions = $superAdminUser->getAllPermissions()->count();

                echo "\n   🔍 SUPER ADMIN VERIFICATION:\n";
                echo "   ├─ User: admin@mawared.com\n";
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
            echo "   ⚠️  WARNING: admin@mawared.com user not found!\n\n";
        }
    }
}
