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
        $user = User::where('email', 'admin@mawared.com')->first();

        if (! $user) {
            User::create([
                'id' => (string) Str::ulid(),
                'name' => 'مدير النظام',
                'email' => 'admin@mawared.com',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]);
        }
    }
}
