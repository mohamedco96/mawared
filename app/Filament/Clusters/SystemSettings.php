<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class SystemSettings extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected static ?string $navigationLabel = 'إعدادات النظام';

    protected static ?string $navigationGroup = 'أخرى'; // Bottom group

    protected static ?int $navigationSort = 2; // Push to bottom

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyPermission([
            'view_user',
            'page_ActivityLogResource',
            'page_GeneralSettings',
            'page_Backups'
        ]) ?? false;
    }
}
