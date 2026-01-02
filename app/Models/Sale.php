<?php

namespace App\Models;

use App\Observers\SaleObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([SaleObserver::class])]
class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'supplier_id',
        'price_package_id',
        'without_supplier',
        'payment_method_id',
        'sale_date',
        'source',
        'status',
        'total_amount',
        'currency',
        'notes',
        'amount_usd',
        'exchange_rate_used',
        'manually_converted',
        'payment_reference',
        'refunded_at',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'total_amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'exchange_rate_used' => 'decimal:6',
        'manually_converted' => 'boolean',
        'without_supplier' => 'boolean',
        'refunded_at' => 'datetime',
    ];

    // Relaciones
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function pricePackage()
    {
        return $this->belongsTo(PricePackage::class);
    }

    /**
     * Verificar si esta venta usa créditos de proveedor
     */
    public function isProviderCredit(): bool
    {
        // Una venta es crédito de proveedor si el método de pago es "Créditos Servidor"
        return $this->paymentMethod && $this->paymentMethod->isServerCredits();
    }

    /**
     * Verificar si esta venta debe debitar balance del proveedor
     */
    public function shouldDebitSupplier(): bool
    {
        // Solo debita si:
        // 1. Es una venta con método de pago "Créditos Servidor"
        // 2. Tiene proveedor asignado
        // 3. NO está marcada como "without_supplier"
        // 4. NO está reembolsada
        return $this->isProviderCredit()
            && $this->supplier_id
            && !$this->without_supplier
            && !$this->isRefunded();
    }

    /**
     * Verificar si esta venta está reembolsada
     */
    public function isRefunded(): bool
    {
        return $this->refunded_at !== null;
    }

    /**
     * Verificar si esta venta puede ser reembolsada
     */
    public function canBeRefunded(): bool
    {
        return $this->isProviderCredit()
            && !$this->isRefunded()
            && $this->shouldDebitSupplier();
    }

    /**
     * Reembolsar esta venta de créditos
     */
    public function refund(): bool
    {
        if (!$this->canBeRefunded()) {
            \Log::warning('⚠️ Intento de reembolso rechazado: venta no puede ser reembolsada', [
                'sale_id' => $this->id,
                'is_provider_credit' => $this->isProviderCredit(),
                'is_refunded' => $this->isRefunded(),
                'should_debit_supplier' => $this->shouldDebitSupplier()
            ]);
            return false;
        }

        // Verificación CRÍTICA: El proveedor debe existir
        if (!$this->supplier) {
            \Log::error('❌ Error al reembolsar: proveedor no encontrado', [
                'sale_id' => $this->id,
                'supplier_id' => $this->supplier_id
            ]);
            return false;
        }

        // Calcular el monto EXACTO a reembolsar (usar base_cost como en SaleObserver)
        $totalBaseCost = $this->items->sum(function ($item) {
            return ($item->base_price ?? 0) * $item->quantity;
        });
        $amountToRefund = $totalBaseCost > 0 ? $totalBaseCost : $this->amount_usd;

        // Verificación: Validar que el monto es coherente
        if ($amountToRefund <= 0) {
            \Log::error('❌ Error al reembolsar: monto inválido', [
                'sale_id' => $this->id,
                'amount_to_refund' => $amountToRefund,
                'amount_usd' => $this->amount_usd
            ]);
            return false;
        }

        // Marcar como reembolsada
        $this->refunded_at = now();
        $this->save();

        // Acreditar el monto de vuelta al proveedor
        $oldBalance = $this->supplier->balance;
        $this->supplier->addToBalance($amountToRefund);

        \Log::info('💰 Venta de créditos reembolsada exitosamente', [
            'sale_id' => $this->id,
            'supplier' => $this->supplier->name,
            'amount_refunded' => $amountToRefund,
            'sale_total' => $this->amount_usd,
            'base_cost_total' => $totalBaseCost,
            'old_balance' => $oldBalance,
            'new_balance' => $this->supplier->fresh()->balance,
        ]);

        return true;
    }
}