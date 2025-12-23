<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el ENUM para incluir 'store'
        DB::statement("ALTER TABLE products MODIFY COLUMN type ENUM('service', 'digital_product', 'server_credit', 'store') DEFAULT 'service'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al ENUM original (solo si no hay registros con tipo 'store')
        DB::statement("ALTER TABLE products MODIFY COLUMN type ENUM('service', 'digital_product', 'server_credit') DEFAULT 'service'");
    }
};
