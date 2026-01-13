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

    public function balanceTransactions()
    {
        return $this->hasMany(SupplierBalanceTransaction::class)->orderBy('created_at', 'desc');
    }

    /**
     * Incrementar balance (cuando se hace un pago al proveedor)
     *
     * @param float $amount
     * @param string $type Tipo de transacción (payment, sale_refund, manual_adjustment)
     * @param string|null $description
     * @param Model|null $reference
     */
    public function addToBalance(
        float $amount,
        string $type = 'payment',
        ?string $description = null,
        ?\Illuminate\Database\Eloquent\Model $reference = null
    ): void {
        $balanceBefore = $this->balance;
        $this->increment('balance', $amount);
        $balanceAfter = $this->fresh()->balance;

        // Registrar transacción para auditoría
        $this->recordTransaction(
            type: $type,
            amount: $amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            description: $description,
            reference: $reference
        );
    }

    /**
     * Decrementar balance (cuando se usa crédito del proveedor)
     *
     * @param float $amount
     * @param string $type Tipo de transacción (sale_debit, manual_adjustment)
     * @param string|null $description
     * @param Model|null $reference
     */
    public function subtractFromBalance(
        float $amount,
        string $type = 'sale_debit',
        ?string $description = null,
        ?\Illuminate\Database\Eloquent\Model $reference = null
    ): void {
        $balanceBefore = $this->balance;
        $this->decrement('balance', $amount);
        $balanceAfter = $this->fresh()->balance;

        // Registrar transacción para auditoría (monto negativo)
        $this->recordTransaction(
            type: $type,
            amount: -$amount,
            balanceBefore: $balanceBefore,
            balanceAfter: $balanceAfter,
            description: $description,
            reference: $reference
        );
    }

    /**
     * Registrar una transacción de balance para auditoría
     */
    private function recordTransaction(
        string $type,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        ?string $description,
        ?\Illuminate\Database\Eloquent\Model $reference
    ): void {
        $this->balanceTransactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference ? $reference->id : null,
            'user_id' => auth()->id(),
        ]);
    }
}