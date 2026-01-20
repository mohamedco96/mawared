<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Database\Seeder;

class UserPreferenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        foreach ($users as $user) {
            // Theme preference
            UserPreference::create([
                'user_id' => $user->id,
                'key' => 'theme',
                'value' => ['mode' => 'light', 'primary_color' => 'blue'],
            ]);

            // Notification preference
            UserPreference::create([
                'user_id' => $user->id,
                'key' => 'notifications',
                'value' => [
                    'email' => true,
                    'push' => true,
                    'sms' => false,
                ],
            ]);

            // Dashboard layout preference
            UserPreference::create([
                'user_id' => $user->id,
                'key' => 'dashboard_layout',
                'value' => [
                    'widgets' => ['sales_chart', 'recent_orders', 'top_products'],
                    'collapsed_sidebar' => false,
                ],
            ]);
        }
    }
}
