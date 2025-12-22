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
        // Agregar campos a la tabla sales
        Schema::table('sales', function (Blueprint $table) {
            $table->decimal('amount_usd', 12, 2)->nullable()->after('total_amount'); // Monto base en USD
            $table->decimal('exchange_rate_used', 12, 6)->nullable()->after('amount_usd'); // Tasa usada
            $table->boolean('manually_converted')->default(false)->after('exchange_rate_used'); // Si fue editado manualmente
            $table->string('payment_reference', 100)->nullable()->after('payment_method_id'); // Referencia de pago
        });

        // Agregar campos a la tabla expenses
        Schema::table('expenses', function (Blueprint $table) {
            $table->decimal('amount_usd', 12, 2)->nullable()->after('amount'); // Monto base en USD
            $table->decimal('exchange_rate_used', 12, 6)->nullable()->after('amount_usd'); // Tasa usada
            $table->boolean('manually_converted')->default(false)->after('exchange_rate_used'); // Si fue editado manualmente
            $table->string('payment_reference', 100)->nullable()->after('payment_method_id'); // Referencia de pago
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['amount_usd', 'exchange_rate_used', 'manually_converted', 'payment_reference']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn(['amount_usd', 'exchange_rate_used', 'manually_converted', 'payment_reference']);
        });
    }
};
