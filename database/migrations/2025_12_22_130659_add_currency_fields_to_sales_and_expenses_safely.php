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
        // Add currency conversion fields to sales table
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'amount_usd')) {
                $table->decimal('amount_usd', 12, 2)->nullable()->after('total_amount');
            }
            if (!Schema::hasColumn('sales', 'exchange_rate_used')) {
                $table->decimal('exchange_rate_used', 12, 6)->nullable()->after('amount_usd');
            }
            if (!Schema::hasColumn('sales', 'manually_converted')) {
                $table->boolean('manually_converted')->default(false)->after('exchange_rate_used');
            }
            if (!Schema::hasColumn('sales', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->after('payment_method_id');
            }
        });

        // Add currency conversion fields to expenses table
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'amount_usd')) {
                $table->decimal('amount_usd', 12, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('expenses', 'exchange_rate_used')) {
                $table->decimal('exchange_rate_used', 12, 6)->nullable()->after('amount_usd');
            }
            if (!Schema::hasColumn('expenses', 'manually_converted')) {
                $table->boolean('manually_converted')->default(false)->after('exchange_rate_used');
            }
            if (!Schema::hasColumn('expenses', 'payment_reference')) {
                $table->string('payment_reference', 100)->nullable()->after('payment_method_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'amount_usd')) {
                $table->dropColumn('amount_usd');
            }
            if (Schema::hasColumn('sales', 'exchange_rate_used')) {
                $table->dropColumn('exchange_rate_used');
            }
            if (Schema::hasColumn('sales', 'manually_converted')) {
                $table->dropColumn('manually_converted');
            }
            if (Schema::hasColumn('sales', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'amount_usd')) {
                $table->dropColumn('amount_usd');
            }
            if (Schema::hasColumn('expenses', 'exchange_rate_used')) {
                $table->dropColumn('exchange_rate_used');
            }
            if (Schema::hasColumn('expenses', 'manually_converted')) {
                $table->dropColumn('manually_converted');
            }
            if (Schema::hasColumn('expenses', 'payment_reference')) {
                $table->dropColumn('payment_reference');
            }
        });
    }
};
