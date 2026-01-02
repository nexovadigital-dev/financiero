<x-filament-panels::page>
    {{-- 1. FILTROS --}}
    <div class="relative">
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
