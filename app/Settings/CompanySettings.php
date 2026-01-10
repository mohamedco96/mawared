<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CompanySettings extends Settings
{
    public string $company_name;
    public string $company_name_english;
    public string $company_address;
    public string $company_phone;
    public string $company_email;
    public ?string $company_tax_number = null;
    public ?string $company_commercial_register = null;
    public string $logo = '';
    public string $currency;
    public string $currency_symbol;
    public int $low_stock_threshold;
    public string $invoice_prefix_sales;
    public string $invoice_prefix_purchase;
    public string $return_prefix_sales;
    public string $return_prefix_purchase;
    public string $transfer_prefix;
    public string $quotation_prefix;
    public bool $enable_multi_warehouse;
    public bool $enable_multi_treasury;
    public int $default_payment_terms_days;
    public bool $allow_negative_stock;
    public bool $auto_approve_stock_adjustments;
    public ?string $business_whatsapp_number = null;

    public static function group(): string
    {
        return 'company';
    }
}
