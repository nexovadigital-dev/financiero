<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'sort_order',
        'currency', // USD o NIO - determina la moneda de los precios y la venta
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Verificar si el paquete está en moneda NIO
     */
    public function isNIO(): bool
    {
        return $this->currency === 'NIO';
    }

    /**
     * Verificar si el paquete está en USD
     */
    public function isUSD(): bool
    {
        return $this->currency === 'USD' || empty($this->currency);
    }

    /**
     * Obtener el símbolo de moneda
     */
    public function getCurrencySymbol(): string
    {
        return match($this->currency) {
            'NIO' => 'C$',
            'USD' => '$',
            default => '$',
        };
    }

    /**
     * Relación: Ventas que usan este paquete
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * Scope: Solo paquetes activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordenados por sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
