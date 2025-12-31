<x-filament-panels::page>
    {{-- CSS para arreglar z-index y problemas visuales --}}
    <style>
        /* Asegurar que el sidebar móvil tenga z-index alto */
        .fi-sidebar {
            z-index: 50 !important;
        }

        .fi-sidebar-nav {
            z-index: 50 !important;
        }

        /* El overlay del sidebar debe estar por encima de dropdowns */
        .fi-sidebar-close-overlay {
            z-index: 45 !important;
        }

        /* El contenido principal debe estar por debajo del sidebar */
        .fi-main {
            z-index: 1;
        }

        /* En móvil, asegurar que el sidebar siempre esté por encima */
        @media (max-width: 1023px) {
            .fi-sidebar {
                z-index: 60 !important;
            }

            .fi-sidebar-close-overlay {
                z-index: 55 !important;
            }
        }

        /* ===== ARREGLOS PARA DATEPICKERS Y SELECTS ===== */

        /* Los datepickers deben tener z-index alto para estar sobre otros campos */
        .fi-fo-date-time-picker .fi-dropdown-panel,
        .flatpickr-calendar {
            z-index: 100 !important;
        }

        /* Los selects deben tener un z-index menor que los datepickers */
        .fi-fo-select .choices__list--dropdown,
        .fi-fo-select [data-headlessui-state],
        .fi-dropdown-panel:not(.fi-fo-date-time-picker .fi-dropdown-panel) {
            z-index: 90 !important;
        }

        /* Asegurar que los campos del formulario no se superpongan incorrectamente */
        .fi-fo-field-wrp {
            position: relative;
        }

        /* Asegurar que la sección de filtros permita overflow visible */
        .fi-section-content {
            overflow: visible !important;
        }

        .fi-section {
            overflow: visible !important;
        }
    </style>

    {{-- 1. FILTROS --}}
    <div class="relative" style="z-index: 10;">
        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>
    </div>

    {{-- 2. WIDGETS DE ESTADÍSTICAS --}}
    <div class="mb-6">
        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
        />
    </div>

    {{-- 3. TABLA DE VENTAS --}}
    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
