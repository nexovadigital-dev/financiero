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
        Schema::table('price_packages', function (Blueprint $table) {
            // Moneda del paquete: USD por defecto, pero puede ser NIO
            // Si el paquete es NIO, los precios de productos para ese paquete están en NIO
            // y la venta debe generarse obligatoriamente en NIO
            $table->string('currency', 10)->default('USD')->after('sort_order')
                ->comment('Moneda del paquete (USD o NIO)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_packages', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
