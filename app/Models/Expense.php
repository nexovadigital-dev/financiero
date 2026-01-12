<?php

namespace App\Models;

use App\Observers\ExpenseObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([ExpenseObserver::class])]
class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_type',
        'expense_name',
        'supplier_id',
        'payment_method_id',
        'amount',
        'currency',
        'payment_date',
        'description',
        'amount_usd',
        'credits_received',
        'exchange_rate_used',
        'manually_converted',
        'payment_reference',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'amount' => 'decimal:2',
        'amount_usd' => 'decimal:2',
        'credits_received' => 'decimal:2',
        'exchange_rate_used' => 'decimal:6',
        'manually_converted' => 'boolean',
    ];

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * Verificar si es un gasto operativo (no pago a proveedor)
     */
    public function isOperational(): bool
    {
        return $this->expense_type === 'operational';
    }

    /**
     * Obtener el nombre a mostrar (proveedor o nombre del gasto)
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isOperational()) {
            return $this->expense_name ?? 'Gasto operativo';
        }

        return $this->supplier?->name ?? 'Proveedor eliminado';
    }
}