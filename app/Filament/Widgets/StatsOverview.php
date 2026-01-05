<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use App\Models\Expense;
use App\Models\PaymentMethod;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?string $pollingInterval = '30s';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // Obtener el ID del método de pago "Créditos Servidor"
        $creditosServidor = PaymentMethod::where('name', 'Créditos Servidor')->first();
        $creditosServidorId = $creditosServidor?->id;

        // Fecha inicio del mes actual
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfDay();

        // =====================================================
        // 1. INGRESOS REALES (Ventas SIN "Créditos Servidor")
        // Dinero que realmente entra (banco, efectivo, Binance, etc.)
        // =====================================================
        $ingresosQuery = Sale::where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startOfMonth)
            ->whereDate('sale_date', '<=', $endOfMonth);

        if ($creditosServidorId) {
            $ingresosQuery->where('payment_method_id', '!=', $creditosServidorId);
        }

        $totalIngresos = $ingresosQuery->sum('amount_usd');
        $cantidadVentasIngresos = $ingresosQuery->count();

        // =====================================================
        // 2. INVERSIONES/PAGOS A PROVEEDORES
        // Dinero que sale para recargar balance con proveedores
        // =====================================================
        $totalInversiones = Expense::whereDate('payment_date', '>=', $startOfMonth)
            ->whereDate('payment_date', '<=', $endOfMonth)
            ->sum('amount_usd');

        $cantidadInversiones = Expense::whereDate('payment_date', '>=', $startOfMonth)
            ->whereDate('payment_date', '<=', $endOfMonth)
            ->count();

        // =====================================================
        // 3. COSTO DE VENTAS (Débito del proveedor)
        // Precio base de TODAS las ventas (siempre en USD)
        // =====================================================
        $costoVentas = Sale::where('status', 'completed')
            ->whereNull('refunded_at')
            ->whereDate('sale_date', '>=', $startOfMonth)
            ->whereDate('sale_date', '<=', $endOfMonth)
            ->with('items')
            ->get()
            ->sum(function ($sale) {
                return $sale->items->sum(function ($item) {
                    return ($item->base_price ?? 0) * $item->quantity;
                });
            });

        // =====================================================
        // 4. VENTAS CON CRÉDITOS (Cliente paga con su saldo)
        // No genera ingreso nuevo, pero sí costo de proveedor
        // =====================================================
        $ventasCreditos = 0;
        $cantidadVentasCreditos = 0;
        if ($creditosServidorId) {
            $ventasCreditos = Sale::where('status', 'completed')
                ->whereNull('refunded_at')
                ->where('payment_method_id', $creditosServidorId)
                ->whereDate('sale_date', '>=', $startOfMonth)
                ->whereDate('sale_date', '<=', $endOfMonth)
                ->sum('amount_usd');

            $cantidadVentasCreditos = Sale::where('status', 'completed')
                ->whereNull('refunded_at')
                ->where('payment_method_id', $creditosServidorId)
                ->whereDate('sale_date', '>=', $startOfMonth)
                ->whereDate('sale_date', '<=', $endOfMonth)
                ->count();
        }

        // =====================================================
        // 5. BALANCE NETO = Ingresos - Inversiones
        // =====================================================
        $balanceNeto = $totalIngresos - $totalInversiones;

        // =====================================================
        // 6. GANANCIA BRUTA = Ingresos - Costo de Ventas
        // =====================================================
        $gananciaBruta = $totalIngresos - $costoVentas;

        // Gráficos de tendencia últimos 7 días
        $incomeChart = $this->getLast7DaysData('income', $creditosServidorId);
        $expenseChart = $this->getLast7DaysData('expense', null);

        return [
            Stat::make('💰 Ingresos del Mes', '$' . number_format($totalIngresos, 2) . ' USD')
                ->description($cantidadVentasIngresos . ' ventas cobradas')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart($incomeChart),

            Stat::make('📤 Inversiones', '$' . number_format($totalInversiones, 2) . ' USD')
                ->description($cantidadInversiones . ' pagos a proveedores')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger')
                ->chart($expenseChart),

            Stat::make('📊 Costo de Ventas', '$' . number_format($costoVentas, 2) . ' USD')
                ->description('Débito del balance proveedor')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('warning'),

            Stat::make('💳 Ventas con Créditos', '$' . number_format($ventasCreditos, 2) . ' USD')
                ->description($cantidadVentasCreditos . ' sin ingreso nuevo')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('info'),

            Stat::make('📈 Balance Neto', '$' . number_format($balanceNeto, 2) . ' USD')
                ->description($balanceNeto >= 0 ? 'Ingresos - Inversiones ↗' : 'Déficit ↘')
                ->descriptionIcon($balanceNeto >= 0 ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-circle')
                ->color($balanceNeto >= 0 ? 'success' : 'danger'),
        ];
    }

    private function getLast7DaysData(string $type, ?int $creditosServidorId): array
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->startOfDay();

            if ($type === 'income') {
                // Solo ventas que NO son con créditos
                $query = Sale::where('status', 'completed')
                    ->whereNull('refunded_at')
                    ->whereDate('sale_date', $date);

                if ($creditosServidorId) {
                    $query->where('payment_method_id', '!=', $creditosServidorId);
                }

                $value = $query->sum('amount_usd');
            } else {
                $value = Expense::whereDate('payment_date', $date)->sum('amount_usd');
            }

            $data[] = $value;
        }

        return $data;
    }
}
