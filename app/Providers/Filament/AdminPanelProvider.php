<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // CSS Personalizado desde archivo
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => Blade::render('<link rel="stylesheet" href="{{ asset(\'css/filament-custom.css\') }}">')
        );

        // CSS Inline para fix de z-index y dropdowns
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<style>
                /* DROPDOWNS GLOBALES - Menú usuario, filtros, acciones */
                [x-data*="dropdown"],
                [x-data*="Dropdown"],
                .fi-dropdown-panel,
                .fi-user-menu-panel,
                [role="menu"] {
                    z-index: 999999 !important;
                    position: fixed !important;
                }

                /* Dropdowns de tabla - filtros y bulk actions */
                .fi-ta .fi-dropdown-panel,
                .fi-ta [role="menu"],
                .fi-ta-actions [x-data] > div[style*="position"],
                [x-data*="tableColumnSearchIndicator"],
                [x-data*="tableFiltersIndicator"] {
                    z-index: 999999 !important;
                    position: fixed !important;
                }

                /* Z-index para dropdowns en formularios */
                .fi-form .fi-dropdown-panel,
                .fi-form [role="menu"],
                .fi-form [role="listbox"] {
                    z-index: 9999 !important;
                }

                /* Modales SIEMPRE por encima de todo */
                .fi-modal,
                [role="dialog"],
                .fi-modal-window {
                    z-index: 99999 !important;
                }

                /* Contenido de modal scrollable */
                .fi-modal-content {
                    z-index: 99999 !important;
                    overflow-y: auto;
                }
            </style>'
        );

        // Meta tags para prevenir indexación en buscadores
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_START,
            fn (): string => Blade::render('
                <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
                <meta name="googlebot" content="noindex, nofollow">
                <meta name="bingbot" content="noindex, nofollow">
                <meta name="description" content="Sistema de administración privado">
            ')
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Pages\Auth\Login::class)
            // COLORES NICAGSM
            ->colors([
                'primary' => '#7cbd2b', // Verde NicaGSM
                'gray' => Color::Slate,
            ])
            // BRANDING
            ->brandName('NicaGSM Admin')
            ->favicon(asset('images/logo.png')) // Usar mismo logo para favicon
            
            // UI / UX
            ->spa()
            ->font('Poppins')
            ->darkMode(true) // Habilitar toggle de modo oscuro en menú de usuario
            ->globalSearch(false) // Deshabilitar búsqueda global

            // MENÚ LATERAL FIJO (Sin opción de colapsar)
            ->sidebarFullyCollapsibleOnDesktop(false)
            ->sidebarCollapsibleOnDesktop(false) // Esto asegura que siempre esté abierto

            // OPCIONAL: Si quieres el menú superior de navegación desactivado para forzar todo al lateral
            ->topNavigation(false)

            // PERFIL DE USUARIO - Habilitar edición de perfil
            ->profile(\App\Filament\Pages\Auth\EditProfile::class)
            ->userMenuItems([
                'profile' => \Filament\Navigation\MenuItem::make()
                    ->label('Mi Perfil')
                    ->icon('heroicon-o-user-circle'),
                'theme' => \Filament\Navigation\MenuItem::make()
                    ->label('Cambiar Tema')
                    ->icon('heroicon-o-moon')
                    ->url(fn () => '#')
                    ->hidden(),
            ])

            // COMPONENTES
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
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