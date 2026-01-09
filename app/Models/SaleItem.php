<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_name', // Nombre guardado para historial aunque se elimine el producto
        'quantity',
        'unit_price',
        'base_price',        // Precio Base USDT (costo en créditos)
        'base_price_nio',    // Precio Base NIO (córdobas para banco)
        'base_price_usd_nic', // Precio Base USD Nicaragua (dólares para banco)
        'package_price',
        'total_price',
        'metadata_values',
    ];

    /**
     * Obtener el nombre del producto (usar product_name guardado o fallback a relación)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->product_name ?? $this->product?->name ?? 'Producto eliminado';
    }

    protected $casts = [
        'metadata_values' => 'array',
        'unit_price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'base_price_nio' => 'decimal:2',
        'base_price_usd_nic' => 'decimal:2',
        'package_price' => 'decimal:2',
        'total_price' => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}