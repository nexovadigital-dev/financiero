<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code',
        'name',
        'symbol',
        'country_code',
        'exchange_rate',
        'is_base',
        'is_active',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:6',
        'is_base' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener el emoji de la bandera del país
     */
    public function getFlagAttribute(): string
    {
        $code = strtoupper($this->country_code);

        // Convertir código de país a emoji de bandera
        // Ejemplo: US -> 🇺🇸, NI -> 🇳🇮
        $offset = 127397;
        $flag = '';

        for ($i = 0; $i < strlen($code); $i++) {
            $flag .= mb_chr($offset + ord($code[$i]));
        }

        return $flag;
    }

    /**
     * Convertir monto desde USD a esta moneda
     */
    public function convertFromUSD(float $amountUSD): float
    {
        if ($this->is_base) {
            return $amountUSD;
        }

        return round($amountUSD * $this->exchange_rate, 2);
    }

    /**
     * Convertir monto desde esta moneda a USD
     */
    public function convertToUSD(float $amount): float
    {
        if ($this->is_base) {
            return $amount;
        }

        return round($amount / $this->exchange_rate, 2);
    }

    /**
     * Obtener representación con bandera
     */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->flag} {$this->code} - {$this->name}";
    }

    /**
     * Obtener solo bandera + código
     */
    public function getShortDisplayAttribute(): string
    {
        return "{$this->flag} {$this->code}";
    }
}
