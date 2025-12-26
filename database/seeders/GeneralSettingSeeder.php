<?php

namespace Database\Seeders;

use App\Models\GeneralSetting;
use Illuminate\Database\Seeder;

class GeneralSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'company_name',
                'value' => 'شركة موارد للأدوات المنزلية',
            ],
            [
                'key' => 'company_name_english',
                'value' => 'Mawared Household Supplies Co.',
            ],
            [
                'key' => 'company_address',
                'value' => 'القاهرة، مصر',
            ],
            [
                'key' => 'company_phone',
                'value' => '0223456789',
            ],
            [
                'key' => 'company_email',
                'value' => 'info@mawared.com',
            ],
            [
                'key' => 'company_tax_number',
                'value' => '123456789',
            ],
            [
                'key' => 'company_commercial_register',
                'value' => '987654321',
            ],
            [
                'key' => 'currency',
                'value' => 'EGP',
            ],
            [
                'key' => 'currency_symbol',
                'value' => 'ج.م',
            ],
            [
                'key' => 'low_stock_threshold',
                'value' => '10',
            ],
            [
                'key' => 'invoice_prefix_sales',
                'value' => 'INV-SALE-',
            ],
            [
                'key' => 'invoice_prefix_purchase',
                'value' => 'INV-PUR-',
            ],
            [
                'key' => 'return_prefix_sales',
                'value' => 'RET-SALE-',
            ],
            [
                'key' => 'return_prefix_purchase',
                'value' => 'RET-PUR-',
            ],
            [
                'key' => 'transfer_prefix',
                'value' => 'TRF-',
            ],
            [
                'key' => 'enable_multi_warehouse',
                'value' => 'true',
            ],
            [
                'key' => 'enable_multi_treasury',
                'value' => 'true',
            ],
            [
                'key' => 'default_payment_terms_days',
                'value' => '30',
            ],
            [
                'key' => 'allow_negative_stock',
                'value' => 'false',
            ],
            [
                'key' => 'auto_approve_stock_adjustments',
                'value' => 'false',
            ],
        ];

        foreach ($settings as $setting) {
            GeneralSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
