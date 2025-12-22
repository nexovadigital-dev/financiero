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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods');
            
            // Datos del pago
            $table->decimal('amount', 10, 2); // Monto pagado
            $table->string('currency')->default('USD'); // Moneda
            $table->date('payment_date'); // Fecha del pago
            $table->string('description')->nullable(); // Concepto o detalle
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};