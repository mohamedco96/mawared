<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class InventorySettings extends Cluster
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'إعدادات المخزون';

    protected static ?string $navigationGroup = 'المخزون';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyPermission([
            'view_product::category',
            'view_unit',
            'view_warehouse'
        ]) ?? false;
    }
}
