<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes; // Importar SoftDeletes

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'price',
        'base_price',
        'price_package_1',
        'price_package_2',
        'price_package_3',
        'price_package_4',
        'type',
        'required_metadata',
        'woocommerce_product_id',
        'sku',
        'is_active',
    ];

    protected $casts = [
        'required_metadata' => 'array',
        'price' => 'decimal:2',
        'base_price' => 'decimal:2',
        'price_package_1' => 'decimal:2',
        'price_package_2' => 'decimal:2',
        'price_package_3' => 'decimal:2',
        'price_package_4' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener el precio según el paquete seleccionado
     */
    public function getPriceForPackage(int $packageId): float
    {
        return match($packageId) {
            1 => $this->price_package_1,
            2 => $this->price_package_2,
            3 => $this->price_package_3,
            4 => $this->price_package_4,
            default => 0,
        };
    }

    /**
     * Verificar si el producto tiene precios configurados
     */
    public function hasPricesConfigured(): bool
    {
        return $this->base_price > 0
            || $this->price_package_1 > 0
            || $this->price_package_2 > 0
            || $this->price_package_3 > 0
            || $this->price_package_4 > 0;
    }

    /**
     * Relación con precios por proveedor
     */
    public function supplierPrices(): HasMany
    {
        return $this->hasMany(ProductSupplierPrice::class);
    }

    /**
     * Relación many-to-many con proveedores a través de precios
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class, 'product_supplier_prices')
            ->withPivot('base_price')
            ->withTimestamps();
    }

    /**
     * Obtener el precio base para un proveedor específico
     */
    public function getBasePriceForSupplier(?int $supplierId): float
    {
        if (!$supplierId) {
            // Si no hay proveedor, usar el precio base antiguo como fallback
            return $this->base_price ?? 0;
        }

        $supplierPrice = $this->supplierPrices()
            ->where('supplier_id', $supplierId)
            ->first();

        return $supplierPrice?->base_price ?? $this->base_price ?? 0;
    }

    /**
     * Obtener precio final para cliente según proveedor y paquete
     */
    public function getFinalPrice(?int $supplierId, int $packageId): float
    {
        $basePrice = $this->getBasePriceForSupplier($supplierId);
        $packageMultiplier = $this->getPriceForPackage($packageId);

        // Si el paquete tiene precio configurado, usarlo
        // Si no, usar el precio base del proveedor
        return $packageMultiplier > 0 ? $packageMultiplier : $basePrice;
    }
}