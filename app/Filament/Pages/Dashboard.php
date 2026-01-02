<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'لوحة التحكم';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getHeading(): string
    {
        return '';
    }
}
