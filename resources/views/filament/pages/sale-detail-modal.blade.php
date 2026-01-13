<div class="space-y-6">
    {{-- Información Básica --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">📋 Información de la Venta</h3>
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">ID Venta</dt>
                <dd class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100">#{{ $sale->id }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Fecha</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $sale->sale_date->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cliente</dt>
                <dd class="mt-1 text-sm text-gray-900 dark:text-gray-100">
                    <span class="flex items-center gap-1">
                        <x-heroicon-o-user-circle class="w-4 h-4" />
                        {{ $sale->client->name ?? 'Sin cliente' }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($sale->status === 'completed') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif($sale->status === 'pending') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @else bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                        @endif">
                        @if($sale->status === 'completed')
                            <x-heroicon-o-check-circle class="w-4 h-4 mr-1" />
                            Completada
                        @elseif($sale->status === 'pending')
                            <x-heroicon-o-clock class="w-4 h-4 mr-1" />
                            Pendiente
                        @else
                            <x-heroicon-o-x-circle class="w-4 h-4 mr-1" />
                            Cancelada
                        @endif
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Método de Pago</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                        {{ $sale->paymentMethod->name ?? 'Sin método' }}
                    </span>
                </dd>
            </div>
            <div>
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Origen</dt>
                <dd class="mt-1">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        @if($sale->source === 'store') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @endif">
                        {{ $sale->source === 'store' ? '🏪 Tienda' : '🖥️ Servidor' }}
                    </span>
                </dd>
            </div>
            @if($sale->supplier)
            <div class="md:col-span-2">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Proveedor</dt>
                <dd class="mt-1 text-sm font-semibold text-blue-600 dark:text-blue-400">
                    {{ $sale->supplier->name }}
                </dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Productos --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">📦 Productos</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Producto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Cantidad</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Precio Base</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Precio Venta</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($sale->items as $item)
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                            {{ $item->display_name }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                            {{ $item->quantity }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                            ${{ number_format($item->base_price ?? 0, 2) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                            @php
                                $currency = $sale->currency ?? 'USD';
                                $symbol = match($currency) {
                                    'NIO' => 'C$',
                                    'USD' => '$',
                                    'USDT' => '$',
                                    default => '$',
                                };
                            @endphp
                            {{ $symbol }}{{ number_format($item->price, 2) }}
                        </td>
                        <td class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $symbol }}{{ number_format($item->price * $item->quantity, 2) }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Totales --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">💰 Resumen Financiero</h3>
        <dl class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Moneda</dt>
                <dd class="mt-2">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                        @if($sale->currency === 'USD') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                        @elseif($sale->currency === 'NIO') bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200
                        @else bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                        @endif">
                        {{ $sale->currency }}
                    </span>
                </dd>
            </div>
            <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Costo Total</dt>
                <dd class="mt-2 text-xl font-bold text-yellow-600 dark:text-yellow-400">
                    @php
                        $totalCost = $sale->items->sum(function($item) {
                            return ($item->base_price ?? 0) * $item->quantity;
                        });
                        $currency = $sale->currency ?? 'USD';
                        $symbol = match($currency) {
                            'NIO' => 'C$',
                            'USD' => '$',
                            'USDT' => '$',
                            default => '$',
                        };
                    @endphp
                    ${{ number_format($totalCost, 2) }} USD
                </dd>
            </div>
            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Venta</dt>
                <dd class="mt-2 text-xl font-bold text-green-600 dark:text-green-400">
                    {{ $symbol }}{{ number_format($sale->total_amount, 2) }} {{ $currency }}
                </dd>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 md:col-span-3">
                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ganancia</dt>
                <dd class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">
                    @php
                        $profit = $sale->total_amount - $totalCost;
                    @endphp
                    {{ $symbol }}{{ number_format($profit, 2) }} {{ $currency }}
                </dd>
            </div>
        </dl>
    </div>

    {{-- Notas (si existen) --}}
    @if($sale->notes)
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-gray-100">📝 Notas</h3>
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $sale->notes }}</p>
    </div>
    @endif
</div>
