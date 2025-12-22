<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    // Esto hace que el widget se actualice cada 15 segundos automáticamente
    protected static ?string $pollingInterval = '15s'; 

    protected function getStats(): array
    {
        // 1. Calcular Ganancia Total (Solo ventas completadas)
        $totalSales = Sale::where('status', 'completed')->sum('total_amount');
        
        // 2. Calcular Ventas de Tienda
        $storeSales = Sale::where('source', 'store')
                          ->where('status', 'completed')
                          ->sum('total_amount');

        // 3. Calcular Ventas de Servidor
        $serverSales = Sale::where('source', 'server')
                           ->where('status', 'completed')
                           ->sum('total_amount');

        return [
            Stat::make('Ganancias Totales (USD)', '$' . number_format($totalSales, 2))
                ->description('Ingresos netos completados')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success') // Verde
                ->chart([7, 2, 10, 3, 15, 4, 17]), // Gráfica decorativa

            Stat::make('Ventas Tienda', '$' . number_format($storeSales, 2))
                ->description('Ingresos físicos/online')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'), // Azul/Verde del tema

            Stat::make('Ventas Servidor', '$' . number_format($serverSales, 2))
                ->description('Créditos y servicios web')
                ->descriptionIcon('heroicon-m-server')
                ->color('warning'), // Amarillo
        ];
    }
}