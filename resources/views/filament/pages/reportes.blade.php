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

    {{-- 3. TABS DE MONEDA --}}
    <div class="mb-4">
        <div class="flex gap-2 flex-wrap">
            @foreach($this->getActiveCurrencies() as $code => $name)
                <button
                    type="button"
                    wire:click="$set('activeTab', '{{ $code }}')"
                    @class([
                        'px-4 py-2 rounded-lg font-medium transition-all duration-200',
                        'bg-primary-600 text-white shadow-md' => $activeTab === $code,
                        'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' => $activeTab !== $code,
                    ])
                >
                    {{ $this->getCurrencySymbol($code) }} {{ $code }}
                    @php
                        $stats = $this->getStatsForCurrency($code);
                    @endphp
                    <span class="ml-2 text-xs opacity-75">
                        ({{ $stats['total_ventas'] }} ventas)
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Estadísticas de la moneda seleccionada --}}
        @php
            $currentStats = $this->getStatsForCurrency($activeTab);
        @endphp
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border-l-4 border-green-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Total Ingresos ({{ $activeTab }})</p>
                <p class="text-2xl font-bold text-green-600">
                    {{ $this->getCurrencySymbol($activeTab) }}{{ number_format($currentStats['total_ingresos'], 2) }}
                </p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border-l-4 border-blue-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Cantidad de Ventas</p>
                <p class="text-2xl font-bold text-blue-600">{{ $currentStats['total_ventas'] }}</p>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow border-l-4 border-purple-500">
                <p class="text-sm text-gray-500 dark:text-gray-400">Promedio por Venta</p>
                <p class="text-2xl font-bold text-purple-600">
                    {{ $this->getCurrencySymbol($activeTab) }}{{ number_format($currentStats['promedio_venta'], 2) }}
                </p>
            </div>
        </div>
    </div>

    {{-- 4. TABLA DE VENTAS --}}
    <div class="mt-6">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
