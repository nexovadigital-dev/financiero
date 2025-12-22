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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            
            // Tipo de producto
            $table->enum('type', ['service', 'digital_product', 'server_credit'])->default('service');
            
            // Metadatos requeridos (JSON para definir campos como IMEI, Serial, etc.)
            $table->json('required_metadata')->nullable(); 
            
            // Integración futura
            $table->unsignedBigInteger('woocommerce_product_id')->nullable();
            $table->string('sku')->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};