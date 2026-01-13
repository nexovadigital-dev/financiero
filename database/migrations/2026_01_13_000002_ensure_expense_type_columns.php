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
        // Verificar y agregar expense_type si no existe
        if (!Schema::hasColumn('expenses', 'expense_type')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('expense_type', 50)->default('supplier_payment')->after('id');
            });
        }

        // Verificar y agregar expense_name si no existe
        if (!Schema::hasColumn('expenses', 'expense_name')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('expense_name')->nullable()->after('expense_type');
            });
        }

        // Actualizar registros existentes que no tengan expense_type
        \Illuminate\Support\Facades\DB::table('expenses')
            ->whereNull('expense_type')
            ->orWhere('expense_type', '')
            ->update(['expense_type' => 'supplier_payment']);

        // Hacer supplier_id nullable si no lo es
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
            if (Schema::hasColumn('expenses', 'expense_type')) {
                $table->dropColumn('expense_type');
            }
            if (Schema::hasColumn('expenses', 'expense_name')) {
                $table->dropColumn('expense_name');
            }
        });
    }
};
