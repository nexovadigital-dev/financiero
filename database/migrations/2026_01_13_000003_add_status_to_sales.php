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
        if (!Schema::hasColumn('sales', 'status')) {
            Schema::table('sales', function (Blueprint $table) {
                $table->string('status', 50)->default('completed')->after('currency');
            });
        }

        // Actualizar todas las ventas existentes a 'completed'
        \Illuminate\Support\Facades\DB::table('sales')
            ->whereNull('status')
            ->orWhere('status', '')
            ->update(['status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
