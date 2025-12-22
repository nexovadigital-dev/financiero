<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class IngresosChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Evolución de Ingresos';
    
    // --- OPTIMIZACIÓN ---
    protected static ?string $pollingInterval = null; 
    protected static bool $isLazy = true; 
    protected static ?int $sort = 1;
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now();
        $currency = $this->filters['currency'] ?? 'USD';

        $data = Trend::query(
                Sale::query()
                    ->where('status', 'completed')
                    ->where('currency', $currency)
            )
            ->between(
                start: Carbon::parse($startDate),
                end: Carbon::parse($endDate),
            )
            ->perDay()
            ->sum('total_amount');

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos (' . $currency . ')',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => '#7cbd2b', 
                    'backgroundColor' => 'rgba(124, 189, 43, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => $value->date),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    // --- ESTO ES LO NUEVO: OCULTAR EN DASHBOARD ---
    public static function canView(): bool
    {
        // Solo se muestra si estamos en la página 'reportes'
        // Si intentara cargar en el Dashboard sin filtros, daría error.
        return request()->routeIs('filament.admin.pages.reportes');
    }
}