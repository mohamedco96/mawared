<?php

namespace Database\Seeders;

use App\Models\Revenue;
use App\Models\Treasury;
use App\Models\User;
use Illuminate\Database\Seeder;

class RevenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $treasury = Treasury::where('name', 'الخزينة الرئيسية')->first();
        $user = User::first();

        $revenues = [
            [
                'title' => 'إيراد متنوع - استشارات',
                'description' => 'دخل من خدمات استشارية',
                'amount' => 3000.00,
                'treasury_id' => $treasury->id,
                'revenue_date' => now()->subDays(8),
                'created_by' => $user->id,
            ],
            [
                'title' => 'عمولة من شراكة تجارية',
                'description' => 'عمولة ربع سنوية',
                'amount' => 5500.00,
                'treasury_id' => $treasury->id,
                'revenue_date' => now()->subDays(4),
                'created_by' => $user->id,
            ],
            [
                'title' => 'أرباح استثمارية',
                'description' => 'أرباح من استثمارات خارجية',
                'amount' => 2200.00,
                'treasury_id' => $treasury->id,
                'revenue_date' => now()->subDay(),
                'created_by' => $user->id,
            ],
        ];

        foreach ($revenues as $revenue) {
            Revenue::create($revenue);
        }
    }
}
