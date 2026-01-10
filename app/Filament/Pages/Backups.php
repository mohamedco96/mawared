<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    protected static ?string $cluster = \App\Filament\Clusters\SystemSettings::class;

    protected static ?string $navigationLabel = 'النسخ الاحتياطي';

    protected static ?string $title = 'النسخ الاحتياطي';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static string $view = 'filament.pages.backups';

    public static function getNavigationGroup(): ?string
    {
        return null; // No group since we're using a cluster
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->can('page_Backups') ?? false;
    }
}
