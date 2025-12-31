<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Mawared ERP')
            ->colors([
                'primary' => Color::Blue,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Cyan,
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                'المبيعات',         // Sales
                'المشتريات',        // Purchases
                'المخزون',          // Inventory
                'الإدارة المالية',  // Financials
                'إعدادات النظام',   // System Settings
            ])
            ->sidebarCollapsibleOnDesktop()
            ->collapsibleNavigationGroups(true)
            ->maxContentWidth('full')
            ->font('Cairo')
            ->renderHook(
                'panels::head.end',
                fn (): string => '
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
                    <style>
                        :root {
                            --font-family-sans: \'Cairo\', ui-sans-serif, system-ui, sans-serif;
                            --font-family-mono: \'Fira Code\', monospace;
                        }
                        /* Apply Cairo font globally for Arabic support */
                        body, .fi-body, .fi-header, .fi-sidebar, .fi-main {
                            font-family: \'Cairo\', ui-sans-serif, system-ui, sans-serif !important;
                        }
                        /* Hide scrollbar for Chrome, Safari and Opera */
                        .fi-sidebar-nav::-webkit-scrollbar {
                            display: none;
                        }
                        /* Hide scrollbar for IE, Edge and Firefox */
                        .fi-sidebar-nav {
                            -ms-overflow-style: none;  /* IE and Edge */
                            scrollbar-width: none;  /* Firefox */
                        }

                        /* Enhanced Sidebar Navigation - Full Background Highlights */
                        .fi-sidebar-nav-item {
                            margin: 0.25rem 0.5rem;
                            border-radius: 0.75rem;
                            transition: all 0.2s ease;
                        }
                        .fi-sidebar-nav-item:hover {
                            background-color: rgba(0, 0, 0, 0.05);
                        }
                        .dark .fi-sidebar-nav-item:hover {
                            background-color: rgba(255, 255, 255, 0.05);
                        }
                        .fi-sidebar-nav-item[aria-current="page"],
                        .fi-sidebar-nav-item.fi-active {
                            background-color: rgb(239 246 255) !important; /* primary-50 */
                            box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
                        }
                        .dark .fi-sidebar-nav-item[aria-current="page"],
                        .dark .fi-sidebar-nav-item.fi-active {
                            background-color: rgba(59, 130, 246, 0.1) !important; /* primary-500/10 */
                        }
                        .fi-sidebar-nav-item[aria-current="page"] .fi-sidebar-item-label,
                        .fi-sidebar-nav-item[aria-current="page"] .fi-sidebar-item-icon,
                        .fi-sidebar-nav-item.fi-active .fi-sidebar-item-label,
                        .fi-sidebar-nav-item.fi-active .fi-sidebar-item-icon {
                            color: rgb(37 99 235) !important; /* primary-600 */
                            font-weight: 600;
                        }
                        .dark .fi-sidebar-nav-item[aria-current="page"] .fi-sidebar-item-label,
                        .dark .fi-sidebar-nav-item[aria-current="page"] .fi-sidebar-item-icon,
                        .dark .fi-sidebar-nav-item.fi-active .fi-sidebar-item-label,
                        .dark .fi-sidebar-nav-item.fi-active .fi-sidebar-item-icon {
                            color: rgb(96 165 250) !important; /* primary-400 */
                        }

                        /* Group spacing */
                        .fi-sidebar-nav-groups {
                            padding: 0.5rem;
                        }
                        .fi-sidebar-group {
                            margin-bottom: 0.5rem;
                        }
                    </style>
                ',
            )
            ->renderHook(
                'panels::body.end',
                fn (): string => '<script src="' . asset('js/filament/fix-arabic-numbers.js') . '"></script>',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
            ->plugin(
                \ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(\App\Filament\Pages\Backups::class)
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
