<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->enum('payment_currency', ['USDT', 'LOCAL'])
                ->default('LOCAL')
                ->after('balance')
                ->comment('Tipo de moneda en que se paga al proveedor');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('payment_currency');
        });
    }
};
