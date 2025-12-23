<?php

namespace Database\Seeders;

use App\Models\Treasury;
use Illuminate\Database\Seeder;

class TreasurySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $treasuries = [
            [
                'name' => 'الخزينة الرئيسية',
                'type' => 'cash',
                'description' => 'خزينة المكتب الرئيسي',
            ],
            [
                'name' => 'خزينة الفرع',
                'type' => 'cash',
                'description' => 'خزينة فرع الإسكندرية',
            ],
            [
                'name' => 'حساب بنكي - البنك الأهلي',
                'type' => 'bank',
                'description' => 'حساب جاري رقم 123456789',
            ],
        ];

        foreach ($treasuries as $treasury) {
            Treasury::create($treasury);
        }
    }
}
