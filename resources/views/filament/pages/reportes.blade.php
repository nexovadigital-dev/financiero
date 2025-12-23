<x-filament-panels::page>
    {{-- 1. FILTROS --}}
    <div>
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