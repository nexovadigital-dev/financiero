<?php

namespace App\Observers;

use App\Models\Sale;
use Illuminate\Support\Facades\Log;

class SaleObserver
{
    /**
     * Handle the Sale "created" event.
     * Debita el balance del proveedor cuando se crea una venta de créditos.
     */
    public function created(Sale $sale): void
    {
        if (!$sale->shouldDebitSupplier()) {
            return;
        }

        // Calcular el monto a debitar basado en el costo base de los items
        $totalBaseCost = $sale->items->sum(function ($item) {
            return ($item->base_price ?? 0) * $item->quantity;
        });

        // Si no hay base_cost, usar amount_usd
        $amountToDebit = $totalBaseCost > 0 ? $totalBaseCost : $sale->amount_usd;

        if ($amountToDebit <= 0 || !$sale->supplier) {
            Log::warning('⚠️ Venta de créditos sin monto válido o proveedor', [
                'sale_id' => $sale->id,
                'amount_to_debit' => $amountToDebit,
                'supplier_id' => $sale->supplier_id,
            ]);
            return;
        }

        $oldBalance = $sale->supplier->balance;
        $sale->supplier->subtractFromBalance(
            amount: $amountToDebit,
            type: 'sale_debit',
            description: "Venta #{$sale->id} - Cliente: {$sale->client->name}",
            reference: $sale
        );

        Log::info('💳 Balance debitado por venta de créditos', [
            'sale_id' => $sale->id,
            'supplier' => $sale->supplier->name,
            'amount_debited' => $amountToDebit,
            'old_balance' => $oldBalance,
            'new_balance' => $sale->supplier->fresh()->balance,
        ]);
    }
}
