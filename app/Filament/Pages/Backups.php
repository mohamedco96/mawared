<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    protected static ?string $navigationLabel = 'النسخ الاحتياطي';

    protected static ?string $title = 'النسخ الاحتياطي';

    protected static ?string $navigationGroup = 'إعدادات النظام';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_Backups') ?? false;
    }
}
