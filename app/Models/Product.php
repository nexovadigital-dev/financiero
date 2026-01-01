<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes; // Importar SoftDeletes

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'base_prices',
        'price_pack_1',
        'price_pack_2',
        'price_pack_3',
        'price_pack_4',
        'type',
        'required_metadata',
        'woocommerce_product_id',
        'sku',
        'is_active',
    ];

    protected $casts = [
        'required_metadata' => 'array',
        'base_prices' => 'array',
        'price' => 'decimal:2',
        'price_pack_1' => 'decimal:2',
        'price_pack_2' => 'decimal:2',
        'price_pack_3' => 'decimal:2',
        'price_pack_4' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // Obtener precio base de un proveedor específico
    public function getBasePriceForSupplier($supplierId): ?float
    {
        if (!$this->base_prices || !isset($this->base_prices[$supplierId])) {
            return null;
        }
        return (float) $this->base_prices[$supplierId];
    }

    // Relación con proveedores
    public function suppliers()
    {
        return Supplier::whereIn('id', array_keys($this->base_prices ?? []))->get();
    }
}