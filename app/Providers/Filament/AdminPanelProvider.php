<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
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
                'primary' => Color::Teal,
            ])
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                'نظرة عامة',        // Overview
                'إدارة المخزون',    // Inventory Management
                'المبيعات',         // Sales
                'المشتريات',        // Purchases
                'المالية والشركاء', // Finance & Partners
                'الإدارة',          // Administration
            ])
            ->sidebarCollapsibleOnDesktop()
            ->font('Cairo')
            ->renderHook(
                'panels::head.end',
                fn (): string => '
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap" rel="stylesheet">
                    <style>
                        :root {
                            --font-family-sans: \'Cairo\', sans-serif;
                            --font-family-mono: \'Fira Code\', monospace;
                        }
                        /* Apply to everything */
                        body, .fi-body, .fi-header, .fi-sidebar, .fi-main {
                            font-family: \'Cairo\', sans-serif !important;
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
