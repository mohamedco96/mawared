<?php

namespace App\Console\Commands;

use App\Models\GeneralSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InitializeCompanySettings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'settings:initialize-company';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize company settings with default values';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Initializing company settings...');

        $settings = [
            'company.company_name' => GeneralSetting::getValue('company_name', 'شركة موارد للأدوات المنزلية'),
            'company.company_name_english' => GeneralSetting::getValue('company_name_english', 'Mawared Household Supplies Co.'),
            'company.company_address' => GeneralSetting::getValue('company_address', 'القاهرة، مصر'),
            'company.company_phone' => GeneralSetting::getValue('company_phone', '0223456789'),
            'company.company_email' => GeneralSetting::getValue('company_email', 'info@mawared.com'),
            'company.company_tax_number' => GeneralSetting::getValue('company_tax_number', '123456789'),
            'company.company_commercial_register' => GeneralSetting::getValue('company_commercial_register', '987654321'),
            'company.logo' => GeneralSetting::getValue('logo', ''),
            'company.currency' => GeneralSetting::getValue('currency', 'EGP'),
            'company.currency_symbol' => GeneralSetting::getValue('currency_symbol', 'ج.م'),
            'company.low_stock_threshold' => (int) GeneralSetting::getValue('low_stock_threshold', '10'),
            'company.invoice_prefix_sales' => GeneralSetting::getValue('invoice_prefix_sales', 'INV-SALE-'),
            'company.invoice_prefix_purchase' => GeneralSetting::getValue('invoice_prefix_purchase', 'INV-PUR-'),
            'company.return_prefix_sales' => GeneralSetting::getValue('return_prefix_sales', 'RET-SALE-'),
            'company.return_prefix_purchase' => GeneralSetting::getValue('return_prefix_purchase', 'RET-PUR-'),
            'company.transfer_prefix' => GeneralSetting::getValue('transfer_prefix', 'TRF-'),
            'company.quotation_prefix' => GeneralSetting::getValue('quotation_prefix', 'QT'),
            'company.enable_multi_warehouse' => GeneralSetting::getValue('enable_multi_warehouse', 'true') === 'true',
            'company.enable_multi_treasury' => GeneralSetting::getValue('enable_multi_treasury', 'true') === 'true',
            'company.default_payment_terms_days' => (int) GeneralSetting::getValue('default_payment_terms_days', '30'),
            'company.allow_negative_stock' => GeneralSetting::getValue('allow_negative_stock', 'false') === 'true',
            'company.auto_approve_stock_adjustments' => GeneralSetting::getValue('auto_approve_stock_adjustments', 'false') === 'true',
        ];

        foreach ($settings as $key => $value) {
            [$group, $name] = explode('.', $key, 2);

            // Check if setting already exists
            $existing = DB::table('settings')
                ->where('group', $group)
                ->where('name', $name)
                ->first();

            if (! $existing) {
                DB::table('settings')->insert([
                    'group' => $group,
                    'name' => $name,
                    'locked' => false,
                    'payload' => json_encode($value),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->line("Added: {$key}");
            } else {
                $this->line("Skipped (exists): {$key}");
            }
        }

        $this->info('Company settings initialized successfully!');

        return Command::SUCCESS;
    }
}
