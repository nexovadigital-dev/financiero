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
        // Migrar todas las ventas a crédito existentes a la tabla de transacciones
        $sales = DB::table('sales')
            ->join('payment_methods', 'sales.payment_method_id', '=', 'payment_methods.id')
            ->where('payment_methods.name', 'LIKE', '%Créditos Servidor%')
            ->whereNotNull('sales.supplier_id')
            ->select('sales.*')
            ->get();

        foreach ($sales as $sale) {
            // Calcular el monto debitado (costo base de los items)
            $totalBaseCost = DB::table('sale_items')
                ->where('sale_id', $sale->id)
                ->selectRaw('SUM(COALESCE(base_price, 0) * quantity) as total')
                ->value('total');

            $amountToDebit = $totalBaseCost > 0 ? $totalBaseCost : ($sale->amount_usd ?? 0);

            if ($amountToDebit <= 0) {
                continue;
            }

            // Obtener el proveedor
            $supplier = DB::table('suppliers')->where('id', $sale->supplier_id)->first();
            if (!$supplier) {
                continue;
            }

            // Obtener el cliente
            $client = DB::table('clients')->where('id', $sale->client_id)->first();
            $clientName = $client ? $client->name : 'Cliente Desconocido';

            // Si la venta está reembolsada, crear DOS transacciones
            if ($sale->refunded_at) {
                // 1. Transacción de débito original (cuando se creó la venta)
                DB::table('supplier_balance_transactions')->insert([
                    'supplier_id' => $sale->supplier_id,
                    'type' => 'sale_debit',
                    'amount' => -$amountToDebit,
                    'balance_before' => 0, // No sabemos el balance histórico
                    'balance_after' => 0,  // No sabemos el balance histórico
                    'reference_type' => 'App\\Models\\Sale',
                    'reference_id' => $sale->id,
                    'description' => "Venta #{$sale->id} - Cliente: {$clientName} (Migrado)",
                    'user_id' => null,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->created_at,
                ]);

                // 2. Transacción de reembolso
                DB::table('supplier_balance_transactions')->insert([
                    'supplier_id' => $sale->supplier_id,
                    'type' => 'sale_refund',
                    'amount' => $amountToDebit,
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'reference_type' => 'App\\Models\\Sale',
                    'reference_id' => $sale->id,
                    'description' => "Reembolso Venta #{$sale->id} - Cliente: {$clientName} (Migrado)",
                    'user_id' => null,
                    'created_at' => $sale->refunded_at,
                    'updated_at' => $sale->refunded_at,
                ]);
            } else {
                // Solo transacción de débito (venta activa)
                DB::table('supplier_balance_transactions')->insert([
                    'supplier_id' => $sale->supplier_id,
                    'type' => 'sale_debit',
                    'amount' => -$amountToDebit,
                    'balance_before' => 0, // No sabemos el balance histórico
                    'balance_after' => 0,  // No sabemos el balance histórico
                    'reference_type' => 'App\\Models\\Sale',
                    'reference_id' => $sale->id,
                    'description' => "Venta #{$sale->id} - Cliente: {$clientName} (Migrado)",
                    'user_id' => null,
                    'created_at' => $sale->created_at,
                    'updated_at' => $sale->created_at,
                ]);
            }
        }

        // Migrar todos los pagos a proveedores existentes
        $expenses = DB::table('expenses')
            ->where('expense_type', 'supplier_payment')
            ->whereNotNull('supplier_id')
            ->get();

        foreach ($expenses as $expense) {
            $creditsReceived = floatval($expense->credits_received ?? 0);

            if ($creditsReceived <= 0) {
                continue;
            }

            DB::table('supplier_balance_transactions')->insert([
                'supplier_id' => $expense->supplier_id,
                'type' => 'payment',
                'amount' => $creditsReceived,
                'balance_before' => 0,
                'balance_after' => 0,
                'reference_type' => 'App\\Models\\Expense',
                'reference_id' => $expense->id,
                'description' => "Pago #{$expense->id} - {$expense->amount} {$expense->currency} → {$creditsReceived} USD (Migrado)",
                'user_id' => null,
                'created_at' => $expense->payment_date,
                'updated_at' => $expense->payment_date,
            ]);
        }

        \Log::info('✅ Migración de transacciones históricas completada', [
            'ventas_migradas' => $sales->count(),
            'pagos_migrados' => $expenses->count(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar solo las transacciones migradas (las que tienen "(Migrado)" en la descripción)
        DB::table('supplier_balance_transactions')
            ->where('description', 'LIKE', '%(Migrado)%')
            ->delete();
    }
};
