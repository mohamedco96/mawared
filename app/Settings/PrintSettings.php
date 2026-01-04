<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class PrintSettings extends Settings
{
    public bool $auto_print_enabled;

    public string $default_print_format; // 'a4' or 'thermal'

    public bool $show_company_logo;

    public bool $show_print_date;

    public int $auto_print_delay_ms;

    public static function group(): string
    {
        return 'print';
    }
}
