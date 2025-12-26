<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
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
            ],
        ];

        foreach ($users as $userData) {
            $existingUser = User::where('email', $userData['email'])->first();
            if (! $existingUser) {
                User::create($userData);
            }
        }
    }
}
