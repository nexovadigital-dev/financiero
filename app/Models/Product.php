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
        'price_package_5',
        'price_package_6',
        'price_package_7',
        'price_package_8',
        'price_package_9',
        'price_package_10',
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
        'price_package_5' => 'decimal:2',
        'price_package_6' => 'decimal:2',
        'price_package_7' => 'decimal:2',
        'price_package_8' => 'decimal:2',
        'price_package_9' => 'decimal:2',
        'price_package_10' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Obtener el precio según el paquete seleccionado
     * Usa mapeo dinámico: el precio se obtiene según el índice del paquete en la lista ordenada,
     * no según su ID (esto permite tener paquetes con IDs mayores a 10)
     */
    public function getPriceForPackage(int $packageId): float
    {
        // Obtener todos los paquetes activos ordenados
        $packages = \App\Models\PricePackage::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('id')
            ->toArray();

        // Buscar el índice del paquete solicitado
        $index = array_search($packageId, $packages);

        // Si no se encuentra el paquete o el índice es >= 10, devolver 0
        if ($index === false || $index >= 10) {
            return 0;
        }

        // Mapear el índice (0-9) al campo correspondiente (price_package_1 a price_package_10)
        $fieldName = 'price_package_' . ($index + 1);

        return $this->{$fieldName} ?? 0;
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
     * Obtener el precio base para un proveedor específico (siempre en USD)
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
     * Obtener el precio base en NIO para un proveedor específico
     * Para exportación al banco en córdobas
     */
    public function getBasePriceNioForSupplier(?int $supplierId): ?float
    {
        if (!$supplierId) {
            return null;
        }

        $supplierPrice = $this->supplierPrices()
            ->where('supplier_id', $supplierId)
            ->first();

        return $supplierPrice?->base_price_nio;
    }

    /**
     * Obtener el precio base en USD Nicaragua para un proveedor específico
     * Para exportación al banco en dólares Nicaragua
     */
    public function getBasePriceUsdNicForSupplier(?int $supplierId): ?float
    {
        if (!$supplierId) {
            return null;
        }

        $supplierPrice = $this->supplierPrices()
            ->where('supplier_id', $supplierId)
            ->first();

        return $supplierPrice?->base_price_usd_nic;
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