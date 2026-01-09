<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'website',
        'balance',
        'payment_currency',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    ];

    /**
     * Verificar si el proveedor cobra en USDT
     */
    public function isUSDT(): bool
    {
        return $this->payment_currency === 'USDT';
    }

    /**
     * Verificar si el proveedor cobra en moneda local
     */
    public function isLocal(): bool
    {
        return $this->payment_currency === 'LOCAL';
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Incrementar balance (cuando se hace un pago al proveedor)
     */
    public function addToBalance(float $amount): void
    {
        $this->increment('balance', $amount);
    }

    /**
     * Decrementar balance (cuando se usa crédito del proveedor)
     */
    public function subtractFromBalance(float $amount): void
    {
        $this->decrement('balance', $amount);
    }
}