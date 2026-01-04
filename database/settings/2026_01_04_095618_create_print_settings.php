<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('print.auto_print_enabled', true);
        $this->migrator->add('print.default_print_format', 'a4');
        $this->migrator->add('print.show_company_logo', true);
        $this->migrator->add('print.show_print_date', true);
        $this->migrator->add('print.auto_print_delay_ms', 500);
    }
};
