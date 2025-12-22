<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class MetodosPagoChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Ingresos por Método de Pago';
    
    // --- OPTIMIZACIÓN DE VELOCIDAD ---
    protected static ?string $pollingInterval = null;
    protected static bool $isLazy = true;
    protected static ?int $sort = 2;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        // 1. Obtener filtros
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now();
        $currency = $this->filters['currency'] ?? 'USD';

        // 2. Consulta Agrupada
        $data = Sale::query()
            ->join('payment_methods', 'sales.payment_method_id', '=', 'payment_methods.id')
            ->select('payment_methods.name', DB::raw('sum(sales.total_amount) as total'))
            ->where('sales.status', 'completed')
            ->where('sales.sale_date', '>=', $startDate)
            ->where('sales.sale_date', '<=', $endDate)
            ->where('sales.currency', $currency)
            ->groupBy('payment_methods.name')
            ->pluck('total', 'payment_methods.name')
            ->toArray();

        // 3. Retornar datos
        return [
            'datasets' => [
                [
                    'label' => 'Total Vendido',
                    'data' => array_values($data),
                    'backgroundColor' => [
                        '#7cbd2b', // Verde Principal
                        '#1e293b', // Oscuro
                        '#f59e0b', // Amarillo
                        '#3b82f6', // Azul
                        '#ef4444', // Rojo
                        '#8b5cf6', // Violeta
                    ],
                    'hoverOffset' => 4,
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}