<x-filament-panels::page>
    {{-- ESTILOS CSS PARA ANIMACIONES --}}
    <style>
        .fade-enter {
            animation: fadeIn 0.6s ease-out forwards;
            opacity: 0;
            transform: translateY(15px);
        }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.3s; }
        .delay-300 { animation-delay: 0.5s; }

        @keyframes fadeIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>

    {{-- 1. FILTROS (Entrada Rápida) --}}
    <div class="fade-enter">
        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>
    </div>
    
    {{-- 2. GRÁFICOS (Entrada Media con Indicador de Carga) --}}
    <div class="mb-6 fade-enter delay-200">
        {{-- Mensaje visual mientras Livewire procesa los cambios --}}
        <div wire:loading.delay class="w-full text-center py-2 text-sm text-gray-500">
            <x-filament::loading-indicator class="h-5 w-5 inline-block mr-2" />
            Actualizando estadísticas...
        </div>

        <x-filament-widgets::widgets
            :widgets="$this->getHeaderWidgets()"
            :columns="$this->getHeaderWidgetsColumns()"
        />
    </div>

    {{-- 3. TABLA (Entrada Final) --}}
    <div class="mt-8 fade-enter delay-300">
        {{ $this->table }}
    </div>
</x-filament-panels::page>