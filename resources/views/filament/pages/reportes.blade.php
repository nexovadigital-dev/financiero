<x-filament-panels::page>
    {{-- 1. FILTROS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mb-6">
        <x-filament-panels::form>
            {{ $this->form }}
        </x-filament-panels::form>
    </div>

    {{-- 2. SELECTOR DE MONEDA --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 sm:p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 text-center">Filtrar por Moneda</h3>
        <div class="flex flex-wrap justify-center gap-2">
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
    </div>

    {{-- 3. INDICADORES FINANCIEROS --}}
    @php
        $financialStats = $this->getFinancialStats();
        $currencySymbol = $activeTab === 'ALL' ? '$' : $this->getCurrencySymbol($activeTab);
        $currencyLabel = $activeTab === 'ALL' ? 'USD' : $activeTab;
    @endphp

    {{-- Primera fila: 3 indicadores principales --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        {{-- INGRESOS (Ventas sin créditos) --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-green-500/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Ingresos Reales</span>
                </div>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ $currencySymbol }}{{ number_format($financialStats['ingresos'], 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $financialStats['ingresos_count'] }} ventas cobradas ({{ $currencyLabel }})
                </p>
            </div>
        </div>

        {{-- COSTO DE VENTAS (Débito proveedor) - SIEMPRE EN USD --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-yellow-500/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 bg-yellow-100 dark:bg-yellow-900/30 rounded-lg">
                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Costo de Ventas</span>
                </div>
                <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">
                    ${{ number_format($financialStats['costo_ventas'] ?? 0, 2) }} USD
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Precio base proveedor (USD)
                </p>
            </div>
        </div>

        {{-- GANANCIA BRUTA --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 {{ ($financialStats['ganancia_bruta'] ?? 0) >= 0 ? 'bg-emerald-500/10' : 'bg-red-500/10' }} rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 {{ ($financialStats['ganancia_bruta'] ?? 0) >= 0 ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-red-100 dark:bg-red-900/30' }} rounded-lg">
                        <svg class="w-5 h-5 {{ ($financialStats['ganancia_bruta'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Ganancia Bruta</span>
                </div>
                <p class="text-2xl font-bold {{ ($financialStats['ganancia_bruta'] ?? 0) >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ ($financialStats['ganancia_bruta'] ?? 0) >= 0 ? '' : '-' }}{{ $currencySymbol }}{{ number_format(abs($financialStats['ganancia_bruta'] ?? 0), 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Ingresos - Costo de Ventas
                </p>
            </div>
        </div>
    </div>

    {{-- Segunda fila: 4 indicadores secundarios --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        {{-- USO DE CRÉDITOS - SIEMPRE EN USD --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-orange-500/10 rounded-full -mr-8 -mt-8"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-1.5 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Uso de Créditos</span>
                </div>
                <p class="text-xl font-bold text-orange-600 dark:text-orange-400">
                    ${{ number_format($financialStats['egresos_creditos'], 2) }} USD
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $financialStats['egresos_count'] }} operaciones (balance proveedor)
                </p>
            </div>
        </div>

        {{-- INVERSIONES - SIEMPRE EN USD --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-red-500/10 rounded-full -mr-8 -mt-8"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-1.5 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Inversiones</span>
                </div>
                <p class="text-xl font-bold text-red-600 dark:text-red-400">
                    ${{ number_format($financialStats['gastos'], 2) }} USD
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $financialStats['gastos_count'] }} pagos a proveedores
                </p>
            </div>
        </div>

        {{-- BALANCE NETO --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 {{ $financialStats['balance_neto'] >= 0 ? 'bg-blue-500/10' : 'bg-red-500/10' }} rounded-full -mr-8 -mt-8"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-1.5 {{ $financialStats['balance_neto'] >= 0 ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-red-100 dark:bg-red-900/30' }} rounded-lg">
                        <svg class="w-4 h-4 {{ $financialStats['balance_neto'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Balance Neto</span>
                </div>
                <p class="text-xl font-bold {{ $financialStats['balance_neto'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $financialStats['balance_neto'] >= 0 ? '' : '-' }}{{ $currencySymbol }}{{ number_format(abs($financialStats['balance_neto']), 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Ingresos - Inversiones
                </p>
            </div>
        </div>

        {{-- TOTAL VENTAS --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-16 h-16 bg-purple-500/10 rounded-full -mr-8 -mt-8"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-1.5 bg-purple-100 dark:bg-purple-900/30 rounded-lg">
                        <svg class="w-4 h-4 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Ventas</span>
                </div>
                <p class="text-xl font-bold text-purple-600 dark:text-purple-400">
                    {{ $financialStats['total_ventas'] }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    operaciones en período
                </p>
            </div>
        </div>
    </div>

    @if($activeTab === 'ALL')
    {{-- Nota para modo TODAS --}}
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 rounded-xl p-3 mb-6 border border-gray-200 dark:border-gray-600">
        <p class="text-center text-xs text-gray-500 dark:text-gray-400">
            💡 En modo "TODAS" los valores se muestran en USD equivalente. Selecciona NIO para ver reportes en córdobas (para el banco).
        </p>
    </div>
    @endif

    {{-- 4. TABLA DE VENTAS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
