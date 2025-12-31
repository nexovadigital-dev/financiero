<x-filament-panels::page>
    {{-- CSS para arreglar z-index del menú móvil --}}
    <style>
        /* Asegurar que el sidebar móvil tenga z-index alto */
        .fi-sidebar-nav {
            z-index: 50 !important;
        }

        /* El overlay del sidebar debe estar por encima de dropdowns */
        .fi-sidebar-close-overlay {
            z-index: 45 !important;
        }

        /* Los dropdowns de select deben tener z-index menor al sidebar */
        .fi-fo-select .choices__list--dropdown,
        .fi-fo-select .fi-select-dropdown,
        [data-headlessui-state] {
            z-index: 40 !important;
        }

        /* En móvil, cuando el sidebar está abierto, bajar z-index de formularios */
        @media (max-width: 1023px) {
            .fi-sidebar[data-open="true"] ~ .fi-main .fi-fo-select .choices__list--dropdown,
            .fi-sidebar[data-open="true"] ~ .fi-main [data-headlessui-state] {
                z-index: 30 !important;
            }
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
        <div wire:loading.delay class="w-full text-center py-2 text-sm text-gray-500">
            <x-filament::loading-indicator class="h-5 w-5 inline-block mr-2" />
            Actualizando estadísticas...
        </div>

        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
        />
    </div>

    {{-- 3. TABLA DE VENTAS --}}
    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
