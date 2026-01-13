<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            // Métodos USD estándar
            ['name' => 'Binance Pay', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'AIRTM', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Kash', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Créditos Servidor', 'currency' => 'USD', 'is_active' => true],

            // Métodos NIO (Córdobas Nicaragua)
            ['name' => 'BAC Nicaragua Córdobas', 'currency' => 'NIO', 'is_active' => true],
            ['name' => 'Lafise Nicaragua Córdobas', 'currency' => 'NIO', 'is_active' => true],
            ['name' => 'Banpro Nicaragua Córdobas', 'currency' => 'NIO', 'is_active' => true],

            // Métodos USD Nicaragua (IMPORTANTE: Usan base_price_usd_nic en ventas)
            ['name' => 'BAC Nicaragua Dólar', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Lafise Nicaragua Dólar', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Banpro Nicaragua Dólar', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Otro Banco Nicaragua Dólar', 'currency' => 'USD', 'is_active' => true],

            // Otros métodos internacionales
            ['name' => 'Pesos Colombianos', 'currency' => 'COP', 'is_active' => true],
            ['name' => 'Pesos Mexicanos', 'currency' => 'MXN', 'is_active' => true],
            ['name' => 'Pesos Chilenos', 'currency' => 'CLP', 'is_active' => true],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::firstOrCreate(
                ['name' => $method['name']],
                [
                    'currency' => $method['currency'],
                    'is_active' => $method['is_active']
                ]
            );
        }

        $this->command->info('✓ Métodos de pago creados/verificados exitosamente');
    }
}
