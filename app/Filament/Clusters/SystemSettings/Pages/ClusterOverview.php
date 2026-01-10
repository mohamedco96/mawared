<?php

namespace App\Filament\Clusters\SystemSettings\Pages;

use App\Filament\Clusters\SystemSettings;
use Filament\Pages\Page;

class ClusterOverview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static string $view = 'filament.clusters.system-settings.pages.cluster-overview';

    protected static ?string $cluster = SystemSettings::class;

    protected static ?string $title = 'نظرة عامة';

    protected static ?string $navigationLabel = 'نظرة عامة';

    protected static ?int $navigationSort = -1;

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Don't show in navigation, just serve as cluster landing page
    }
}
