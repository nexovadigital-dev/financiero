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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('client_id')->constrained('clients');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods');
            
            // Datos generales
            $table->dateTime('sale_date');
            $table->enum('source', ['store', 'server']); // Tienda o Servidor
            $table->enum('status', ['pending', 'processing', 'completed', 'cancelled'])->default('pending');
            
            // Totales
            $table->decimal('total_amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};