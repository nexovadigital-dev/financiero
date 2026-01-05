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

    {{-- 3. TABS DE MONEDA Y ESTADÍSTICAS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mb-6">
        {{-- Tabs de Moneda --}}
        <div class="flex flex-wrap justify-center gap-2 mb-6">
            @foreach($this->getActiveCurrencies() as $code => $name)
                @php
                    $stats = $this->getStatsForCurrency($code);
                @endphp
                <button
                    type="button"
                    wire:click="$set('activeTab', '{{ $code }}')"
                    @class([
                        'inline-flex items-center px-4 py-2 rounded-lg font-medium text-sm transition-all duration-200',
                        'bg-primary-600 text-white shadow-md ring-2 ring-primary-600 ring-offset-2' => $activeTab === $code,
                        'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600' => $activeTab !== $code,
                    ])
                >
                    @if($code === 'ALL')
                        <span class="mr-1">🌐</span> TODAS
                    @else
                        <span class="mr-1">{{ $this->getCurrencySymbol($code) }}</span> {{ $code }}
                    @endif
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full {{ $activeTab === $code ? 'bg-white/20' : 'bg-gray-200 dark:bg-gray-600' }}">
                        {{ $stats['total_ventas'] }}
                    </span>
                </button>
            @endforeach
        </div>

        {{-- Estadísticas de la moneda seleccionada --}}
        @php
            $currentStats = $this->getStatsForCurrency($activeTab);
            $currencySymbol = $activeTab === 'ALL' ? '$' : $this->getCurrencySymbol($activeTab);
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {{-- Total Ingresos --}}
            <div class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/20 dark:to-green-800/20 rounded-xl p-4 border border-green-200 dark:border-green-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-600 dark:text-green-400">
                            Total Ingresos
                            @if($activeTab === 'ALL')
                                <span class="text-xs opacity-75">(USD eq.)</span>
                            @else
                                <span class="text-xs opacity-75">({{ $activeTab }})</span>
                            @endif
                        </p>
                        <p class="text-2xl sm:text-3xl font-bold text-green-700 dark:text-green-300 mt-1">
                            {{ $currencySymbol }}{{ number_format($currentStats['total_ingresos'], 2) }}
                        </p>
                    </div>
                    <div class="text-green-500 dark:text-green-400">
                        <svg class="w-10 h-10 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Cantidad de Ventas --}}
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/20 dark:to-blue-800/20 rounded-xl p-4 border border-blue-200 dark:border-blue-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-600 dark:text-blue-400">Cantidad de Ventas</p>
                        <p class="text-2xl sm:text-3xl font-bold text-blue-700 dark:text-blue-300 mt-1">
                            {{ $currentStats['total_ventas'] }}
                        </p>
                    </div>
                    <div class="text-blue-500 dark:text-blue-400">
                        <svg class="w-10 h-10 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                </div>
            </div>

            {{-- Promedio por Venta --}}
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-xl p-4 border border-purple-200 dark:border-purple-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-purple-600 dark:text-purple-400">
                            Promedio/Venta
                            @if($activeTab === 'ALL')
                                <span class="text-xs opacity-75">(USD)</span>
                            @endif
                        </p>
                        <p class="text-2xl sm:text-3xl font-bold text-purple-700 dark:text-purple-300 mt-1">
                            {{ $currencySymbol }}{{ number_format($currentStats['promedio_venta'], 2) }}
                        </p>
                    </div>
                    <div class="text-purple-500 dark:text-purple-400">
                        <svg class="w-10 h-10 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        @if($activeTab === 'ALL')
            <div class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/50 rounded-lg py-2 px-4">
                💡 <strong>Vista global:</strong> Mostrando todas las ventas. Totales en USD equivalente.
            </div>
        @endif
    </div>

    {{-- 4. TABLA DE VENTAS --}}
    <div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
