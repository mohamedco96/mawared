<?php

use App\Models\GeneralSetting;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        // Create all company settings with values from GeneralSetting or defaults
        $this->migrator->add('company.company_name', GeneralSetting::getValue('company_name', 'شركة موارد للأدوات المنزلية'));
        $this->migrator->add('company.company_name_english', GeneralSetting::getValue('company_name_english', 'Mawared Household Supplies Co.'));
        $this->migrator->add('company.company_address', GeneralSetting::getValue('company_address', 'القاهرة، مصر'));
        $this->migrator->add('company.company_phone', GeneralSetting::getValue('company_phone', '0223456789'));
        $this->migrator->add('company.company_email', GeneralSetting::getValue('company_email', 'info@mawared.com'));
        $this->migrator->add('company.company_tax_number', GeneralSetting::getValue('company_tax_number', null));
        $this->migrator->add('company.company_commercial_register', GeneralSetting::getValue('company_commercial_register', null));
        $this->migrator->add('company.logo', GeneralSetting::getValue('logo', ''));
        $this->migrator->add('company.currency', GeneralSetting::getValue('currency', 'EGP'));
        $this->migrator->add('company.currency_symbol', GeneralSetting::getValue('currency_symbol', 'ج.م'));
        $this->migrator->add('company.low_stock_threshold', (int) GeneralSetting::getValue('low_stock_threshold', '10'));
        $this->migrator->add('company.invoice_prefix_sales', GeneralSetting::getValue('invoice_prefix_sales', 'INV-SALE-'));
        $this->migrator->add('company.invoice_prefix_purchase', GeneralSetting::getValue('invoice_prefix_purchase', 'INV-PUR-'));
        $this->migrator->add('company.return_prefix_sales', GeneralSetting::getValue('return_prefix_sales', 'RET-SALE-'));
        $this->migrator->add('company.return_prefix_purchase', GeneralSetting::getValue('return_prefix_purchase', 'RET-PUR-'));
        $this->migrator->add('company.transfer_prefix', GeneralSetting::getValue('transfer_prefix', 'TRF-'));
        $this->migrator->add('company.quotation_prefix', GeneralSetting::getValue('quotation_prefix', 'QT'));
        $this->migrator->add('company.enable_multi_warehouse', GeneralSetting::getValue('enable_multi_warehouse', 'true') === 'true');
        $this->migrator->add('company.enable_multi_treasury', GeneralSetting::getValue('enable_multi_treasury', 'true') === 'true');
        $this->migrator->add('company.default_payment_terms_days', (int) GeneralSetting::getValue('default_payment_terms_days', '30'));
        $this->migrator->add('company.allow_negative_stock', GeneralSetting::getValue('allow_negative_stock', 'false') === 'true');
        $this->migrator->add('company.auto_approve_stock_adjustments', GeneralSetting::getValue('auto_approve_stock_adjustments', 'false') === 'true');
    }
};
