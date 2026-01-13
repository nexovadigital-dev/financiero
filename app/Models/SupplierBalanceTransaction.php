<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierBalanceTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'description',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    // Relaciones
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    // Helpers para tipos
    public function isPayment(): bool
    {
        return $this->type === 'payment';
    }

    public function isSaleDebit(): bool
    {
        return $this->type === 'sale_debit';
    }

    public function isSaleRefund(): bool
    {
        return $this->type === 'sale_refund';
    }

    public function isManualAdjustment(): bool
    {
        return $this->type === 'manual_adjustment';
    }

    // Helper para obtener el nombre del tipo en español
    public function getTypeNameAttribute(): string
    {
        return match($this->type) {
            'payment' => 'Pago a Proveedor',
            'sale_debit' => 'Venta a Crédito',
            'sale_refund' => 'Reembolso de Venta',
            'manual_adjustment' => 'Ajuste Manual',
            default => $this->type,
        };
    }

    // Helper para obtener el color del badge
    public function getTypeColorAttribute(): string
    {
        return match($this->type) {
            'payment' => 'success',           // Verde
            'sale_debit' => 'danger',         // Rojo
            'sale_refund' => 'warning',       // Amarillo
            'manual_adjustment' => 'info',    // Azul
            default => 'gray',
        };
    }

    // Helper para obtener el ícono
    public function getTypeIconAttribute(): string
    {
        return match($this->type) {
            'payment' => 'heroicon-o-arrow-up-circle',
            'sale_debit' => 'heroicon-o-arrow-down-circle',
            'sale_refund' => 'heroicon-o-arrow-uturn-left',
            'manual_adjustment' => 'heroicon-o-adjustments-horizontal',
            default => 'heroicon-o-circle-stack',
        };
    }
}
