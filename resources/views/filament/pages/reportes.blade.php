<x-filament-panels::page>
    {{-- CSS para arreglar z-index y problemas visuales --}}
    <style>
        /* ===== SIDEBAR MÓVIL ===== */
        .fi-sidebar {
            z-index: 50 !important;
        }

        .fi-sidebar-nav {
            z-index: 50 !important;
        }

        .fi-sidebar-close-overlay {
            z-index: 45 !important;
        }

        .fi-main {
            z-index: 1;
        }

        @media (max-width: 1023px) {
            .fi-sidebar {
                z-index: 60 !important;
            }

            .fi-sidebar-close-overlay {
                z-index: 55 !important;
            }
        }

        /* ===== DATEPICKERS - SIEMPRE HACIA ARRIBA ===== */

        /* Forzar que el calendario siempre se abra hacia arriba */
        .fi-fo-date-time-picker .fi-dropdown-panel {
            z-index: 100 !important;
            background-color: white !important;
            border-radius: 0.75rem !important;
            box-shadow: 0 -10px 25px -5px rgba(0, 0, 0, 0.15), 0 -8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #e5e7eb !important;
            /* Posicionar hacia arriba */
            bottom: 100% !important;
            top: auto !important;
            margin-bottom: 0.5rem !important;
            margin-top: 0 !important;
        }

        /* Dark mode para DatePicker */
        .dark .fi-fo-date-time-picker .fi-dropdown-panel {
            background-color: #1f2937 !important;
            border-color: #374151 !important;
        }

        /* Asegurar fondo sólido en todos los elementos internos */
        .fi-fo-date-time-picker .fi-dropdown-panel * {
            background-color: inherit;
        }

        .fi-fo-date-time-picker .fi-dropdown-panel > div {
            background-color: white !important;
            border-radius: 0.75rem !important;
        }

        .dark .fi-fo-date-time-picker .fi-dropdown-panel > div {
            background-color: #1f2937 !important;
        }

        /* ===== SELECTS ===== */
        .fi-fo-select .choices__list--dropdown,
        .fi-fo-select [data-headlessui-state],
        .fi-dropdown-panel:not(.fi-fo-date-time-picker .fi-dropdown-panel) {
            z-index: 90 !important;
        }

        /* ===== OVERFLOW VISIBLE EN SECCIONES ===== */
        .fi-section-content {
            overflow: visible !important;
        }

        .fi-section {
            overflow: visible !important;
        }

        .fi-fo-field-wrp {
            position: relative;
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
