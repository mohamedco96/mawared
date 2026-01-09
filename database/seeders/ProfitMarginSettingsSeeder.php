<?php

namespace Database\Seeders;

use App\Models\GeneralSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProfitMarginSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'profit_margin_excellent' => 25,
            'profit_margin_good' => 15,
            'profit_margin_warning_below_cost' => true,
        ];

        foreach ($settings as $key => $value) {
            GeneralSetting::setValue($key, $value);
        }

        $this->command->info('تم إضافة إعدادات هامش الربح بنجاح.');
    }
}
