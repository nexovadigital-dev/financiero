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
            // Métodos USD
            ['name' => 'Binance Pay', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'AIRTM', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Kash', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Créditos Servidor', 'currency' => 'USD', 'is_active' => true],

            // Métodos NIO (Nicaragua)
            ['name' => 'Bac-Lafise-Banpro', 'currency' => 'NIO', 'is_active' => true],
            ['name' => 'Transferencia BAC Nicaragua', 'currency' => 'NIO', 'is_active' => true],
            ['name' => 'Transferencia Lafise Nicaragua', 'currency' => 'NIO', 'is_active' => true],
            ['name' => 'Transferencia Banpro Nicaragua', 'currency' => 'NIO', 'is_active' => true],
            ['name' => 'Transferencia Banco Nicaragua', 'currency' => 'NIO', 'is_active' => true],

            // Métodos USD Nicaragua
            ['name' => 'Transferencia BAC USD', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Transferencia Lafise USD', 'currency' => 'USD', 'is_active' => true],
            ['name' => 'Transferencia Banpro USD', 'currency' => 'USD', 'is_active' => true],

            // Otros métodos
            ['name' => 'Pesos Colombianos', 'currency' => 'COP', 'is_active' => true],
            ['name' => 'Pesos Mexicanos', 'currency' => 'MXN', 'is_active' => true],
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

        $this->command->info('✓ Métodos de pago creados exitosamente');
    }
}
