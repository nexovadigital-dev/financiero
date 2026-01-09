<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSupplierPrice extends Model
{
    protected $fillable = [
        'product_id',
        'supplier_id',
        'base_price',        // Precio Base USDT (costo en créditos)
        'base_price_nio',    // Precio Base NIO (córdobas para banco)
        'base_price_usd_nic', // Precio Base USD Nicaragua (dólares para banco)
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'base_price_nio' => 'decimal:2',
        'base_price_usd_nic' => 'decimal:2',
    ];

    /**
     * Relación con el producto
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relación con el proveedor
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
