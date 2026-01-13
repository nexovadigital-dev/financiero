<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Log::info('🔍 Iniciando recálculo de balances históricos');

        // Obtener todos los proveedores
        $suppliers = DB::table('suppliers')->get();

        foreach ($suppliers as $supplier) {
            // Obtener todas las transacciones de este proveedor ordenadas por fecha
            $transactions = DB::table('supplier_balance_transactions')
                ->where('supplier_id', $supplier->id)
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            if ($transactions->isEmpty()) {
                continue;
            }

            // Iniciar con balance en 0
            $runningBalance = 0.00;

            foreach ($transactions as $transaction) {
                $balanceBefore = $runningBalance;

                // Aplicar el cambio según el tipo
                if (in_array($transaction->type, ['payment', 'sale_refund'])) {
                    // Pagos y reembolsos AUMENTAN el balance
                    $runningBalance += abs($transaction->amount);
                } else {
                    // sale_debit y manual_adjustment (negativos) REDUCEN el balance
                    $runningBalance += $transaction->amount; // amount ya viene negativo
                }

                $balanceAfter = $runningBalance;

                // Actualizar el registro
                DB::table('supplier_balance_transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'balance_before' => round($balanceBefore, 2),
                        'balance_after' => round($balanceAfter, 2),
                        'updated_at' => now(),
                    ]);
            }

            \Log::info("✅ Balance recalculado para proveedor {$supplier->name}", [
                'supplier_id' => $supplier->id,
                'transacciones' => $transactions->count(),
                'balance_final' => $runningBalance,
            ]);
        }

        \Log::info('✅ Recálculo de balances históricos completado');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No se puede revertir el recálculo
    }
};
