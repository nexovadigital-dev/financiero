<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\Expense;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    // Esto hace que el widget se actualice cada 30 segundos automáticamente
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // INGRESOS: Calcular total en USD (usa amount_usd si está disponible, si no, usa total_amount)
        $totalIncome = Sale::where('status', 'completed')
            ->get()
            ->sum(function ($sale) {
                return $sale->amount_usd ?? $sale->total_amount;
            });

        // EGRESOS: Calcular total en USD
        $totalExpenses = Expense::all()
            ->sum(function ($expense) {
                return $expense->amount_usd ?? $expense->amount;
            });

        // GANANCIA NETA
        $netProfit = $totalIncome - $totalExpenses;

        // Ventas de tienda (en USD)
        $storeSales = Sale::where('source', 'store')
            ->where('status', 'completed')
            ->get()
            ->sum(function ($sale) {
                return $sale->amount_usd ?? $sale->total_amount;
            });

        // Ventas de servidor (en USD)
        $serverSales = Sale::where('source', 'server')
            ->where('status', 'completed')
            ->get()
            ->sum(function ($sale) {
                return $sale->amount_usd ?? $sale->total_amount;
            });

        // Gráficos de tendencia últimos 7 días
        $incomeChart = $this->getLast7DaysData('income');
        $expenseChart = $this->getLast7DaysData('expense');

        return [
            Stat::make('Total Ingresos', '$' . number_format($totalIncome, 2) . ' USD')
                ->description('Ventas cobradas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($incomeChart),

            Stat::make('Total Egresos', '$' . number_format($totalExpenses, 2) . ' USD')
                ->description('Pagos a proveedores')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart($expenseChart),

            Stat::make('Ganancia Neta', '$' . number_format($netProfit, 2) . ' USD')
                ->description($netProfit >= 0 ? 'Rentabilidad Positiva ↗' : 'Rentabilidad Negativa ↘')
                ->descriptionIcon($netProfit >= 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle')
                ->color($netProfit >= 0 ? 'success' : 'warning'),

            Stat::make('Ventas Tienda', '$' . number_format($storeSales, 2))
                ->description('Ingresos físicos/online')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),

            Stat::make('Ventas Servidor', '$' . number_format($serverSales, 2))
                ->description('Créditos y servicios web')
                ->descriptionIcon('heroicon-m-server')
                ->color('warning'),
        ];
    }

    private function getLast7DaysData(string $type): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();

            if ($type === 'income') {
                $value = Sale::where('status', 'completed')
                    ->whereDate('sale_date', $date)
                    ->get()
                    ->sum(function ($sale) {
                        return $sale->amount_usd ?? $sale->total_amount;
                    });
            } else {
                $value = Expense::whereDate('payment_date', $date)
                    ->get()
                    ->sum(function ($expense) {
                        return $expense->amount_usd ?? $expense->amount;
                    });
            }

            $data[] = $value;
        }

        return $data;
    }
}