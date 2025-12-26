<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Customers
        $customers = [
            [
                'name' => 'أحمد محمد علي',
                'phone' => '01012345678',
                'type' => 'customer',
                'gov_id' => 'القاهرة',
                'region' => 'مدينة نصر',
                'is_banned' => false,
                'current_balance' => 0,
            ],
            [
                'name' => 'محمود حسن',
                'phone' => '01123456789',
                'type' => 'customer',
                'gov_id' => 'الإسكندرية',
                'region' => 'سموحة',
                'is_banned' => false,
                'current_balance' => 1500.00,
            ],
            [
                'name' => 'فاطمة عبد الرحمن',
                'phone' => '01234567890',
                'type' => 'customer',
                'gov_id' => 'الجيزة',
                'region' => 'المهندسين',
                'is_banned' => false,
                'current_balance' => -500.00,
            ],
            [
                'name' => 'شركة النيل للتجارة',
                'phone' => '01098765432',
                'type' => 'customer',
                'gov_id' => 'القاهرة',
                'region' => 'وسط البلد',
                'is_banned' => false,
                'current_balance' => 0,
            ],
        ];

        // Suppliers
        $suppliers = [
            [
                'name' => 'شركة الأهرام للمواد الغذائية',
                'phone' => '0233445566',
                'type' => 'supplier',
                'gov_id' => 'القاهرة',
                'region' => 'العبور',
                'is_banned' => false,
                'current_balance' => 0,
            ],
            [
                'name' => 'مصنع المشروبات الوطنية',
                'phone' => '0245678901',
                'type' => 'supplier',
                'gov_id' => 'الإسكندرية',
                'region' => 'برج العرب',
                'is_banned' => false,
                'current_balance' => -3000.00,
            ],
            [
                'name' => 'موزع الألبان المصرية',
                'phone' => '0212345678',
                'type' => 'supplier',
                'gov_id' => 'الجيزة',
                'region' => '6 أكتوبر',
                'is_banned' => false,
                'current_balance' => -1200.00,
            ],
        ];

        // Shareholders
        $shareholders = [
            [
                'name' => 'محمد أحمد - شريك مؤسس',
                'phone' => '01111111111',
                'type' => 'shareholder',
                'gov_id' => 'القاهرة',
                'region' => 'مصر الجديدة',
                'is_banned' => false,
                'current_balance' => 0,
            ],
            [
                'name' => 'أحمد علي - شريك مساهم',
                'phone' => '01222222222',
                'type' => 'shareholder',
                'gov_id' => 'الجيزة',
                'region' => 'الدقي',
                'is_banned' => false,
                'current_balance' => 0,
            ],
        ];

        foreach ($customers as $customer) {
            Partner::create($customer);
        }

        foreach ($suppliers as $supplier) {
            Partner::create($supplier);
        }

        foreach ($shareholders as $shareholder) {
            Partner::create($shareholder);
        }
    }
}
