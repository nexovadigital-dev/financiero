<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Verificar si las columnas ya existen antes de agregarlas
            if (!Schema::hasColumn('expenses', 'expense_type')) {
                // Tipo de gasto: 'supplier_payment' o 'operational'
                $table->string('expense_type', 50)->default('supplier_payment')->after('id');
            }

            if (!Schema::hasColumn('expenses', 'expense_name')) {
                // Nombre del gasto (para gastos operativos)
                $table->string('expense_name')->nullable()->after('expense_type');
            }
        });

        // Actualizar registros existentes para que tengan expense_type = 'supplier_payment'
        \Illuminate\Support\Facades\DB::table('expenses')
            ->whereNull('expense_type')
            ->orWhere('expense_type', '')
            ->update(['expense_type' => 'supplier_payment']);

        // Ahora hacer supplier_id nullable (después de agregar las columnas)
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['expense_type', 'expense_name']);

            // Revertir supplier_id a no nullable (esto puede fallar si hay datos)
            $table->foreignId('supplier_id')->nullable(false)->change();
        });
    }
};
