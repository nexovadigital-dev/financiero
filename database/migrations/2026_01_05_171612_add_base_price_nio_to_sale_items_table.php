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
        Schema::table('sale_items', function (Blueprint $table) {
            // Precio base en NIO para reportes al banco (solo para proveedor Moneda Local)
            $table->decimal('base_price_nio', 10, 2)->nullable()->after('base_price')
                ->comment('Precio base en córdobas para reportes al banco');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('base_price_nio');
        });
    }
};
