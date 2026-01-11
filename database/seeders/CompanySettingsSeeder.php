<?php

namespace Database\Seeders;

use App\Settings\CompanySettings;
use Illuminate\Database\Seeder;

class CompanySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = app(CompanySettings::class);

        // Company Information
        $settings->company_name = 'شركة الرحاب للأدوات المنزلية';
        $settings->company_name_english = 'Al-Rehab Household Supplies Co.';
        $settings->company_address = 'دمياط';
        $settings->company_phone = '+201006911275';
        $settings->company_email = 'info@osool.com';
        $settings->company_tax_number = null;
        $settings->company_commercial_register = null;
        $settings->logo = '';

        // Currency Settings
        $settings->currency = 'EGP';
        $settings->currency_symbol = 'ج.م';

        // Digital Catalog Settings (إعدادات الكتالوج الرقمي)
        $settings->business_whatsapp_number = '+201006911275';

        // Document Prefixes
        $settings->invoice_prefix_sales = 'INV-SALE-';
        $settings->invoice_prefix_purchase = 'INV-PUR-';
        $settings->return_prefix_sales = 'RET-SALE-';
        $settings->return_prefix_purchase = 'RET-PUR-';
        $settings->transfer_prefix = 'TRF-';
        $settings->quotation_prefix = 'QT';

        // System Settings
        $settings->low_stock_threshold = 10;
        $settings->default_payment_terms_days = 30;
        $settings->enable_multi_warehouse = true;
        $settings->enable_multi_treasury = true;
        $settings->allow_negative_stock = false;
        $settings->auto_approve_stock_adjustments = false;

        $settings->save();
    }
}
