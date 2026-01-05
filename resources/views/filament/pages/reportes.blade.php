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

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Ingresos</span>
                </div>
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">
                    {{ $currencySymbol }}{{ number_format($financialStats['ingresos'], 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $financialStats['ingresos_count'] }} ventas ({{ $currencyLabel }})
                </p>
            </div>
        </div>

        {{-- EGRESOS POR CRÉDITOS --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-orange-500/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 bg-orange-100 dark:bg-orange-900/30 rounded-lg">
                        <svg class="w-5 h-5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Uso de Créditos</span>
                </div>
                <p class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                    {{ $currencySymbol }}{{ number_format($financialStats['egresos_creditos'], 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $financialStats['egresos_count'] }} operaciones
                </p>
            </div>
        </div>

        {{-- GASTOS/INVERSIONES --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 bg-red-500/10 rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Gastos/Inversiones</span>
                </div>
                <p class="text-2xl font-bold text-red-600 dark:text-red-400">
                    {{ $currencySymbol }}{{ number_format($financialStats['gastos'], 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    {{ $financialStats['gastos_count'] }} pagos a proveedores
                </p>
            </div>
        </div>

        {{-- BALANCE NETO --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-20 h-20 {{ $financialStats['balance_neto'] >= 0 ? 'bg-blue-500/10' : 'bg-red-500/10' }} rounded-full -mr-10 -mt-10"></div>
            <div class="relative">
                <div class="flex items-center gap-2 mb-2">
                    <div class="p-2 {{ $financialStats['balance_neto'] >= 0 ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-red-100 dark:bg-red-900/30' }} rounded-lg">
                        <svg class="w-5 h-5 {{ $financialStats['balance_neto'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Balance Neto</span>
                </div>
                <p class="text-2xl font-bold {{ $financialStats['balance_neto'] >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $financialStats['balance_neto'] >= 0 ? '' : '-' }}{{ $currencySymbol }}{{ number_format(abs($financialStats['balance_neto']), 2) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Ingresos - Gastos
                </p>
            </div>
        </div>
    </div>

    {{-- RESUMEN ADICIONAL --}}
    <div class="bg-gradient-to-r from-gray-50 to-gray-100 dark:from-gray-800 dark:to-gray-700 rounded-xl p-4 mb-6 border border-gray-200 dark:border-gray-600">
        <div class="flex flex-wrap justify-center gap-6 text-sm">
            <div class="text-center">
                <span class="text-gray-500 dark:text-gray-400">Total Ventas:</span>
                <span class="font-bold text-gray-900 dark:text-white ml-1">{{ $financialStats['total_ventas'] }}</span>
            </div>
            <div class="text-center">
                <span class="text-gray-500 dark:text-gray-400">Moneda:</span>
                <span class="font-bold text-gray-900 dark:text-white ml-1">{{ $activeTab === 'ALL' ? 'Todas (USD eq.)' : $activeTab }}</span>
            </div>
            @if($activeTab === 'ALL')
                <div class="text-center text-xs text-gray-400">
                    💡 Los valores en "TODAS" se muestran en USD equivalente para comparación
                </div>
            @endif
        </div>
    </div>

    {{-- 4. TABLA DE VENTAS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        {{ $this->table }}
    </div>
</x-filament-panels::page>
