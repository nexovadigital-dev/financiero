<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nombre del paquete de precios');
            $table->text('description')->nullable()->comment('Descripción del paquete');
            $table->boolean('is_active')->default(true)->comment('Si el paquete está activo');
            $table->integer('sort_order')->default(0)->comment('Orden de visualización');
            $table->timestamps();
        });

        // Insertar 4 paquetes predeterminados
        DB::table('price_packages')->insert([
            [
                'name' => 'Paquete Premium',
                'description' => 'Precio para clientes premium',
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Paquete Mayorista',
                'description' => 'Precio para clientes mayoristas',
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Paquete Minorista',
                'description' => 'Precio para clientes minoristas',
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Paquete Especial',
                'description' => 'Precio especial personalizado',
                'is_active' => true,
                'sort_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('price_packages');
    }
};
