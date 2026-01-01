<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Precio base (costo del proveedor)
            $table->decimal('base_price', 10, 2)->default(0)->after('price')
                ->comment('Precio base / costo del proveedor');

            // Precios por paquete
            $table->decimal('price_package_1', 10, 2)->default(0)->after('base_price')
                ->comment('Precio para paquete 1 (Premium)');

            $table->decimal('price_package_2', 10, 2)->default(0)->after('price_package_1')
                ->comment('Precio para paquete 2 (Mayorista)');

            $table->decimal('price_package_3', 10, 2)->default(0)->after('price_package_2')
                ->comment('Precio para paquete 3 (Minorista)');

            $table->decimal('price_package_4', 10, 2)->default(0)->after('price_package_3')
                ->comment('Precio para paquete 4 (Especial)');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'base_price',
                'price_package_1',
                'price_package_2',
                'price_package_3',
                'price_package_4',
            ]);
        });
    }
};
