<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;

class ShieldPermissionsProvider implements HasShieldPermissions
{
    public static function getPermissions(): array
    {
        return [
            'Backup Operations' => [
                'download-backup',
                'delete-backup',
                'restore-backup',
            ],
        ];
    }
}
