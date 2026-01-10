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
        Schema::table('products', function (Blueprint $table) {
            // Agregar columnas para más paquetes de precios (5-10)
            $table->decimal('price_package_5', 10, 2)->nullable()->after('price_package_4');
            $table->decimal('price_package_6', 10, 2)->nullable()->after('price_package_5');
            $table->decimal('price_package_7', 10, 2)->nullable()->after('price_package_6');
            $table->decimal('price_package_8', 10, 2)->nullable()->after('price_package_7');
            $table->decimal('price_package_9', 10, 2)->nullable()->after('price_package_8');
            $table->decimal('price_package_10', 10, 2)->nullable()->after('price_package_9');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'price_package_5',
                'price_package_6',
                'price_package_7',
                'price_package_8',
                'price_package_9',
                'price_package_10',
            ]);
        });
    }
};
