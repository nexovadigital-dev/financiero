<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            // Precio base (lo que se cobra al proveedor)
            $table->decimal('base_price', 10, 2)->default(0)->after('unit_price')
                ->comment('Precio base del producto (costo del proveedor)');

            // Precio del paquete (lo que se cobra al cliente)
            $table->decimal('package_price', 10, 2)->default(0)->after('base_price')
                ->comment('Precio del paquete seleccionado (lo que se cobra al cliente)');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn(['base_price', 'package_price']);
        });
    }
};
