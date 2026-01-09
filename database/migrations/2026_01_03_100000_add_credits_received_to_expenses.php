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
            // Créditos recibidos - lo que se suma al balance del proveedor
            $table->decimal('credits_received', 12, 2)->default(0)->after('amount_usd')
                ->comment('Créditos recibidos del proveedor (se suma al balance)');
        });

        // Migrar datos existentes: usar amount_usd como credits_received si existe
        \DB::table('expenses')
            ->whereNull('credits_received')
            ->orWhere('credits_received', 0)
            ->update([
                'credits_received' => \DB::raw('COALESCE(amount_usd, amount, 0)')
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('credits_received');
        });
    }
};
