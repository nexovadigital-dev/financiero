<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\Client;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class DashboardStats extends BaseWidget
{
    // Recarga cada 30 segundos para ver ventas nuevas en tiempo real
    protected static ?string $pollingInterval = '30s';
    
    // Ordenamos para que salga arriba
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Ventas de HOY
        $ventasHoy = Sale::whereDate('sale_date', Carbon::today())
            ->where('status', 'completed')
            ->sum('total_amount');
            
        // 2. Ventas de AYER (Para comparar)
        $ventasAyer = Sale::whereDate('sale_date', Carbon::yesterday())
            ->where('status', 'completed')
            ->sum('total_amount');

        // Calculamos tendencia (Icono arriba o abajo)
        $icon = $ventasHoy >= $ventasAyer ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down';
        $color = $ventasHoy >= $ventasAyer ? 'success' : 'danger';

        // 3. Total Clientes
        $totalClientes = Client::count();

        // 4. Ventas del MES
        $ventasMes = Sale::whereMonth('sale_date', Carbon::now()->month)
            ->where('status', 'completed')
            ->sum('total_amount');

        return [
            Stat::make('Ventas de Hoy', '$' . number_format($ventasHoy, 2))
                ->description('Comparado con ayer: $' . number_format($ventasAyer, 2))
                ->descriptionIcon($icon)
                ->color($color)
                ->chart([7, 2, 10, 3, 15, 4, 17]), // Gráfica decorativa

            Stat::make('Ingresos este Mes', '$' . number_format($ventasMes, 2))
                ->description('Total acumulado')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('primary'),

            Stat::make('Base de Clientes', $totalClientes)
                ->description('Clientes registrados')
                ->descriptionIcon('heroicon-m-users')
                ->color('info'),
        ];
    }
}