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

        // CSS Inline GLOBAL - Fix de z-index para dropdowns sin romper funcionalidad
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => '<style>
                /* ========================================
                   FIX GLOBAL: Z-INDEX SIN ROMPER ALPINE.JS
                   ======================================== */

                /* IMPORTANTE: No usar position:fixed - rompe Alpine.js */

                /* SECCIONES - Overflow visible para que dropdowns no se corten */
                .fi-section,
                .fi-section-content,
                .fi-fo-field-wrp {
                    overflow: visible !important;
                }

                /* DATEPICKERS - Fondo sólido y z-index alto */
                .fi-fo-date-time-picker [x-ref="panel"] {
                    z-index: 50 !important;
                    background: white !important;
                    border-radius: 0.75rem !important;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2) !important;
                    border: 1px solid #e5e7eb !important;
                }

                .dark .fi-fo-date-time-picker [x-ref="panel"] {
                    background: #1f2937 !important;
                    border-color: #374151 !important;
                }

                /* SELECTORES - z-index para listbox */
                [role="listbox"] {
                    z-index: 50 !important;
                }

                /* Modales - z-index más alto que todo */
                .fi-modal {
                    z-index: 100 !important;
                }

                /* Backdrop del modal */
                .fi-modal-close-overlay,
                [x-data] > div[aria-hidden="true"] {
                    z-index: 99 !important;
                }

                /* Contenido del modal sobre el backdrop */
                .fi-modal-window {
                    z-index: 101 !important;
                }

                /* Selectores y datepickers DENTRO de modales - mayor z-index */
                .fi-modal [role="listbox"],
                .fi-modal .fi-fo-date-time-picker [x-ref="panel"] {
                    z-index: 102 !important;
                }

                /* Notificaciones siempre visibles */
                .fi-notification {
                    z-index: 200 !important;
                }

                /* Dropdown del menú de usuario */
                .fi-dropdown-panel {
                    z-index: 50 !important;
                }
            </style>'
        );

        // JavaScript para manejar sesión expirada
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => '<script>
                // Interceptar errores de sesión expirada
                document.addEventListener("livewire:init", () => {
                    Livewire.hook("request", ({ fail }) => {
                        fail(({ status, content }) => {
                            if (status === 419 || status === 401 || status === 403) {
                                // Crear modal de sesión expirada
                                const modal = document.createElement("div");
                                modal.innerHTML = `
                                    <div style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999999;display:flex;align-items:center;justify-content:center;">
                                        <div style="background:white;padding:2rem;border-radius:1rem;max-width:400px;text-align:center;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);">
                                            <svg style="width:64px;height:64px;margin:0 auto 1rem;color:#f59e0b;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                            </svg>
                                            <h2 style="font-size:1.25rem;font-weight:600;color:#1f2937;margin-bottom:0.5rem;">Sesión Expirada</h2>
                                            <p style="color:#6b7280;margin-bottom:1.5rem;">Tu sesión ha caducado por inactividad. Por favor, inicia sesión nuevamente.</p>
                                            <button onclick="window.location.href=\'/admin/login\'" style="background:#7cbd2b;color:white;padding:0.75rem 2rem;border-radius:0.5rem;font-weight:500;border:none;cursor:pointer;">
                                                Iniciar Sesión
                                            </button>
                                        </div>
                                    </div>
                                `;
                                document.body.appendChild(modal);
                            }
                        });
                    });
                });
            </script>'
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

        // Footer con copyright en la página de login
        FilamentView::registerRenderHook(
            PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
            fn (): string => '<div class="text-center text-sm text-gray-500 dark:text-gray-400 mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">© ' . date('Y') . ' NicaGSM - Todos los derechos reservados</div>'
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