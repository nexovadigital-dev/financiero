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
        // Primero, eliminar transacciones migradas anteriormente para evitar duplicados
        DB::table('supplier_balance_transactions')
            ->where('description', 'LIKE', '%(Migrado)%')
            ->delete();

        \Log::info('🔍 Iniciando migración de transacciones históricas - TODAS las ventas con proveedor');

        // Migrar TODAS las ventas que tienen proveedor conectado (sin importar método de pago)
        $sales = DB::table('sales')
            ->whereNotNull('supplier_id')
            ->get();

        \Log::info('📊 Ventas con proveedor encontradas para migrar', [
            'total_ventas' => $sales->count(),
            'ventas_ids' => $sales->pluck('id')->toArray()
        ]);

        $salesMigrated = 0;
        foreach ($sales as $sale) {
            // Calcular el monto debitado (costo base de los items)
            $totalBaseCost = DB::table('sale_items')
                ->where('sale_id', $sale->id)
                ->selectRaw('SUM(COALESCE(base_price, 0) * quantity) as total')
                ->value('total');

            $amountToDebit = $totalBaseCost > 0 ? $totalBaseCost : ($sale->amount_usd ?? 0);

            if ($amountToDebit <= 0) {
                \Log::warning('⚠️ Venta sin monto a debitar', ['sale_id' => $sale->id]);
                continue;
            }

            // Obtener el proveedor
            $supplier = DB::table('suppliers')->where('id', $sale->supplier_id)->first();
            if (!$supplier) {
                \Log::warning('⚠️ Proveedor no encontrado', ['sale_id' => $sale->id, 'supplier_id' => $sale->supplier_id]);
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
                $salesMigrated += 2; // Contamos ambas transacciones
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
                $salesMigrated++;
            }
        }

        \Log::info('✅ Ventas migradas', ['transacciones_creadas' => $salesMigrated]);

        // Migrar todos los pagos a proveedores existentes
        $expenses = DB::table('expenses')
            ->where('expense_type', 'supplier_payment')
            ->whereNotNull('supplier_id')
            ->get();

        \Log::info('📊 Pagos encontrados para migrar', [
            'total_pagos' => $expenses->count(),
        ]);

        $paymentsMigrated = 0;
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
            $paymentsMigrated++;
        }

        \Log::info('✅ Migración de transacciones históricas completada', [
            'ventas_encontradas' => $sales->count(),
            'transacciones_ventas_creadas' => $salesMigrated,
            'pagos_encontrados' => $expenses->count(),
            'transacciones_pagos_creadas' => $paymentsMigrated,
            'total_transacciones' => $salesMigrated + $paymentsMigrated,
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
