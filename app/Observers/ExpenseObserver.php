<?php

namespace App\Observers;

use App\Models\Expense;
use Illuminate\Support\Facades\Log;

class ExpenseObserver
{
    /**
     * Handle the Expense "created" event.
     * Acredita el balance del proveedor cuando se registra un pago.
     */
    public function created(Expense $expense): void
    {
        if (!$expense->supplier_id || !$expense->supplier) {
            return;
        }

        // Usar amount_usd para mantener consistencia con el sistema
        $amountToCredit = $expense->amount_usd > 0 ? $expense->amount_usd : $expense->amount;

        if ($amountToCredit <= 0) {
            Log::warning('⚠️ Pago a proveedor sin monto válido', [
                'expense_id' => $expense->id,
                'amount' => $expense->amount,
                'amount_usd' => $expense->amount_usd,
            ]);
            return;
        }

        $oldBalance = $expense->supplier->balance;
        $expense->supplier->addToBalance($amountToCredit);

        Log::info('💰 Balance acreditado por pago a proveedor', [
            'expense_id' => $expense->id,
            'supplier' => $expense->supplier->name,
            'amount_credited' => $amountToCredit,
            'old_balance' => $oldBalance,
            'new_balance' => $expense->supplier->fresh()->balance,
        ]);
    }

    /**
     * Handle the Expense "deleted" event.
     * Revierte el balance del proveedor cuando se elimina un pago.
     */
    public function deleted(Expense $expense): void
    {
        if (!$expense->supplier_id || !$expense->supplier) {
            return;
        }

        $amountToDebit = $expense->amount_usd > 0 ? $expense->amount_usd : $expense->amount;

        if ($amountToDebit <= 0) {
            return;
        }

        $oldBalance = $expense->supplier->balance;
        $expense->supplier->subtractFromBalance($amountToDebit);

        Log::info('🔄 Balance revertido por eliminación de pago', [
            'expense_id' => $expense->id,
            'supplier' => $expense->supplier->name,
            'amount_debited' => $amountToDebit,
            'old_balance' => $oldBalance,
            'new_balance' => $expense->supplier->fresh()->balance,
        ]);
    }
}
