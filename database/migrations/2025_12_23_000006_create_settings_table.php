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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Nombre único de la configuración');
            $table->text('value')->nullable()->comment('Valor de la configuración (puede ser encriptado)');
            $table->string('group')->default('general')->comment('Grupo de configuración (api, general, etc.)');
            $table->boolean('is_encrypted')->default(false)->comment('Si el valor está encriptado');
            $table->timestamps();
        });

        // Insertar credenciales iniciales desde .env si existen
        $settings = [
            // WooCommerce
            ['key' => 'woocommerce_url', 'value' => env('WOOCOMMERCE_URL'), 'group' => 'api', 'is_encrypted' => false],
            ['key' => 'woocommerce_consumer_key', 'value' => env('WOOCOMMERCE_CONSUMER_KEY'), 'group' => 'api', 'is_encrypted' => true],
            ['key' => 'woocommerce_consumer_secret', 'value' => env('WOOCOMMERCE_CONSUMER_SECRET'), 'group' => 'api', 'is_encrypted' => true],
            
            // DHRU
            ['key' => 'dhru_api_url', 'value' => env('DHRU_API_URL'), 'group' => 'api', 'is_encrypted' => false],
            ['key' => 'dhru_api_key', 'value' => env('DHRU_API_KEY'), 'group' => 'api', 'is_encrypted' => true],
            ['key' => 'dhru_username', 'value' => env('DHRU_USERNAME'), 'group' => 'api', 'is_encrypted' => false],
        ];

        foreach ($settings as $setting) {
            if ($setting['value']) {
                DB::table('settings')->insert([
                    'key' => $setting['key'],
                    'value' => $setting['is_encrypted'] ? encrypt($setting['value']) : $setting['value'],
                    'group' => $setting['group'],
                    'is_encrypted' => $setting['is_encrypted'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
