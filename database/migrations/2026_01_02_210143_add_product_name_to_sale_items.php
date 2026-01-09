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
            // Guardar nombre del producto para mantener histórico aunque se elimine el producto
            $table->string('product_name')->nullable()->after('product_id')
                ->comment('Nombre del producto al momento de la venta');
        });

        // Actualizar registros existentes con el nombre actual del producto
        \DB::statement("
            UPDATE sale_items si
            SET product_name = (SELECT name FROM products WHERE id = si.product_id)
            WHERE product_name IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('product_name');
        });
    }
};
