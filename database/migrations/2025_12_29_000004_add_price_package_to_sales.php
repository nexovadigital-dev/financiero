<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('price_package_id')->nullable()->after('supplier_id')
                ->constrained('price_packages')->nullOnDelete()
                ->comment('Paquete de precios utilizado en esta venta');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['price_package_id']);
            $table->dropColumn('price_package_id');
        });
    }
};
