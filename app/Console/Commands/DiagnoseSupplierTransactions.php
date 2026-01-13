<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiagnoseSupplierTransactions extends Command
{
    protected $signature = 'supplier:diagnose-transactions';
    protected $description = 'Diagnosticar por qué no se están migrando las transacciones de proveedor';

    public function handle()
    {
        $this->info('🔍 DIAGNÓSTICO DE TRANSACCIONES DE PROVEEDORES');
        $this->newLine();

        // 1. Métodos de pago disponibles
        $this->info('1️⃣ MÉTODOS DE PAGO DISPONIBLES:');
        $paymentMethods = DB::table('payment_methods')->get();
        foreach ($paymentMethods as $pm) {
            $this->line("   ID: {$pm->id} | Nombre: '{$pm->name}' | Moneda: {$pm->currency}");
        }
        $this->newLine();

        // 2. Ventas con proveedor
        $this->info('2️⃣ VENTAS CON PROVEEDOR CONECTADO:');
        $salesWithSupplier = DB::table('sales')
            ->whereNotNull('supplier_id')
            ->get();
        $this->line("   Total: {$salesWithSupplier->count()} ventas");

        if ($salesWithSupplier->count() > 0) {
            $this->line("   IDs: " . $salesWithSupplier->pluck('id')->implode(', '));
            $this->line("   Payment Method IDs usados: " . $salesWithSupplier->pluck('payment_method_id')->unique()->implode(', '));
        }
        $this->newLine();

        // 3. Intentar el JOIN como en la migración
        $this->info('3️⃣ VENTAS CON PROVEEDOR + JOIN PAYMENT_METHODS:');
        $salesWithJoin = DB::table('sales')
            ->join('payment_methods', 'sales.payment_method_id', '=', 'payment_methods.id')
            ->whereNotNull('sales.supplier_id')
            ->select('sales.id as sale_id', 'sales.supplier_id', 'payment_methods.id as pm_id', 'payment_methods.name as pm_name')
            ->get();

        $this->line("   Total después del JOIN: {$salesWithJoin->count()} ventas");
        if ($salesWithJoin->count() > 0) {
            foreach ($salesWithJoin as $sale) {
                $this->line("   Venta #{$sale->sale_id} | Proveedor: {$sale->supplier_id} | Método: '{$sale->pm_name}'");
            }
        }
        $this->newLine();

        // 4. Buscar con el filtro actual
        $this->info('4️⃣ VENTAS QUE COINCIDEN CON EL FILTRO DE LA MIGRACIÓN:');
        $salesMatching = DB::table('sales')
            ->join('payment_methods', 'sales.payment_method_id', '=', 'payment_methods.id')
            ->where(function($query) {
                $query->where('payment_methods.name', 'LIKE', '%Créditos Servidor%')
                      ->orWhere('payment_methods.name', 'LIKE', '%Creditos Servidor%')
                      ->orWhere('payment_methods.name', 'LIKE', '%crédito%')
                      ->orWhere('payment_methods.name', 'LIKE', '%credito%');
            })
            ->whereNotNull('sales.supplier_id')
            ->select('sales.id as sale_id', 'payment_methods.name as pm_name')
            ->get();

        $this->line("   Total que coinciden: {$salesMatching->count()} ventas");
        if ($salesMatching->count() > 0) {
            foreach ($salesMatching as $sale) {
                $this->line("   Venta #{$sale->sale_id} | Método: '{$sale->pm_name}'");
            }
        }
        $this->newLine();

        // 5. Verificar transacciones ya migradas
        $this->info('5️⃣ TRANSACCIONES YA MIGRADAS:');
        $migratedCount = DB::table('supplier_balance_transactions')
            ->where('description', 'LIKE', '%(Migrado)%')
            ->count();
        $this->line("   Total migradas anteriormente: {$migratedCount}");
        $this->newLine();

        // 6. Proveedores disponibles
        $this->info('6️⃣ PROVEEDORES DISPONIBLES:');
        $suppliers = DB::table('suppliers')->get();
        $this->line("   Total proveedores: {$suppliers->count()}");
        foreach ($suppliers as $supplier) {
            $this->line("   ID: {$supplier->id} | Nombre: {$supplier->name}");
        }
        $this->newLine();

        $this->info('✅ Diagnóstico completado');
        return 0;
    }
}
