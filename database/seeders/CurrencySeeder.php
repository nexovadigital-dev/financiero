<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            [
                'code' => 'USD',
                'name' => 'Dólar Estadounidense',
                'symbol' => '$',
                'country_code' => 'US',
                'exchange_rate' => 1.000000, // Base currency
                'is_base' => true,
                'is_active' => true,
            ],
            [
                'code' => 'NIO',
                'name' => 'Córdoba Nicaragüense',
                'symbol' => 'C$',
                'country_code' => 'NI',
                'exchange_rate' => 37.000000, // 1 USD = 37 NIO (aproximado, actualizable)
                'is_base' => false,
                'is_active' => true,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'country_code' => 'EU',
                'exchange_rate' => 0.920000, // 1 USD = 0.92 EUR (aproximado, actualizable)
                'is_base' => false,
                'is_active' => false, // Desactivado por defecto
            ],
            [
                'code' => 'MXN',
                'name' => 'Peso Mexicano',
                'symbol' => 'MX$',
                'country_code' => 'MX',
                'exchange_rate' => 17.000000, // 1 USD = 17 MXN (aproximado)
                'is_base' => false,
                'is_active' => false,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
