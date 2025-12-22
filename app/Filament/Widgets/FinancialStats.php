<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\Expense; // Importamos el modelo de Gastos
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Carbon\Carbon;

class FinancialStats extends BaseWidget
{
    use InteractsWithPageFilters; // ¡Vital para leer los filtros de la página!

    // Carga diferida para que no trabe la página al entrar
    protected static bool $isLazy = true;
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        // 1. Obtener los filtros activos de la página Reportes
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth();
        $endDate = $this->filters['endDate'] ?? now();
        $currency = $this->filters['currency'] ?? 'USD';

        // 2. Calcular INGRESOS (Ventas Completadas)
        $ingresos = Sale::query()
            ->where('status', 'completed')
            ->where('currency', $currency)
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate)
            ->sum('total_amount');

        // 3. Calcular EGRESOS (Pagos a Proveedores)
        $egresos = Expense::query()
            ->where('currency', $currency)
            ->whereDate('payment_date', '>=', $startDate)
            ->whereDate('payment_date', '<=', $endDate)
            ->sum('amount');

        // 4. Calcular GANANCIA NETA
        $balance = $ingresos - $egresos;

        // Determinar color y mensaje según el balance
        $colorBalance = $balance >= 0 ? 'success' : 'danger';
        $iconBalance = $balance >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';

        return [
            Stat::make('Total Ingresos', number_format($ingresos, 2) . ' ' . $currency)
                ->description('Ventas cobradas')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart([7, 10, 12, 14, 15, 18, 20]), // Gráfica estética ascendente

            Stat::make('Total Egresos', number_format($egresos, 2) . ' ' . $currency)
                ->description('Pagos a proveedores')
                ->descriptionIcon('heroicon-m-arrow-right-start-on-rectangle')
                ->color('danger')
                ->chart([2, 5, 3, 6, 4, 8, 5]), // Gráfica estética variable

            Stat::make('Ganancia Neta', number_format($balance, 2) . ' ' . $currency)
                ->description($balance >= 0 ? 'Rentabilidad Positiva' : 'Pérdida en el periodo')
                ->descriptionIcon($iconBalance)
                ->color($colorBalance) // Verde si ganas, Rojo si pierdes
                ->extraAttributes([
                    'class' => 'cursor-pointer ring-2 ring-primary-500', // Resaltar esta tarjeta
                ]),
        ];
    }
}