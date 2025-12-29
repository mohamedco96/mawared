<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No actions for read-only resource
        ];
    }

    protected function resolveRecord($key): Model
    {
        return static::getResource()::resolveRecordRouteBinding($key)
            ->load(['subject', 'causer']);
    }
}
