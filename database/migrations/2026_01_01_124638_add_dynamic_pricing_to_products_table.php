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
            // Precios base por proveedor (JSON: {"supplier_id": precio})
            $table->json('base_prices')->nullable()->after('price');

            // 4 precios de venta por paquete
            $table->decimal('price_pack_1', 10, 2)->nullable()->after('base_prices');
            $table->decimal('price_pack_2', 10, 2)->nullable()->after('price_pack_1');
            $table->decimal('price_pack_3', 10, 2)->nullable()->after('price_pack_2');
            $table->decimal('price_pack_4', 10, 2)->nullable()->after('price_pack_3');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['base_prices', 'price_pack_1', 'price_pack_2', 'price_pack_3', 'price_pack_4']);
        });
    }
};
