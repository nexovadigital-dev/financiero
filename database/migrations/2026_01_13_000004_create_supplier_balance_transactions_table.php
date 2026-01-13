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
        Schema::create('supplier_balance_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->onDelete('cascade');

            // Tipo de transacción
            $table->enum('type', [
                'payment',           // Pago realizado al proveedor (+crédito)
                'sale_debit',        // Venta a crédito (-crédito)
                'sale_refund',       // Reembolso de venta (+crédito)
                'manual_adjustment'  // Ajuste manual (+/- crédito)
            ]);

            // Monto de la transacción (positivo o negativo)
            $table->decimal('amount', 10, 2);

            // Balance antes y después (para auditoría)
            $table->decimal('balance_before', 10, 2);
            $table->decimal('balance_after', 10, 2);

            // Referencia polimórfica (puede ser Expense, Sale, etc.)
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            // Descripción/Motivo de la transacción
            $table->text('description')->nullable();

            // Usuario que realizó la transacción
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->index(['supplier_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_balance_transactions');
    }
};
