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

        // Usar credits_received para el balance (la cantidad de créditos que recibimos)
        $creditsToAdd = floatval($expense->credits_received ?? 0);

        if ($creditsToAdd <= 0) {
            Log::warning('⚠️ Pago a proveedor sin créditos a recibir', [
                'expense_id' => $expense->id,
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'credits_received' => $expense->credits_received,
            ]);
            return;
        }

        $oldBalance = $expense->supplier->balance;
        $expense->supplier->addToBalance($creditsToAdd);

        Log::info('💰 Balance acreditado por pago a proveedor', [
            'expense_id' => $expense->id,
            'supplier' => $expense->supplier->name,
            'amount_paid' => $expense->amount . ' ' . $expense->currency,
            'credits_added' => $creditsToAdd,
            'old_balance' => $oldBalance,
            'new_balance' => $expense->supplier->fresh()->balance,
        ]);
    }

    /**
     * Handle the Expense "updated" event.
     * Ajusta el balance si se modifican los créditos recibidos.
     */
    public function updated(Expense $expense): void
    {
        if (!$expense->supplier_id || !$expense->supplier) {
            return;
        }

        // Verificar si credits_received cambió
        if ($expense->isDirty('credits_received')) {
            $oldCredits = floatval($expense->getOriginal('credits_received') ?? 0);
            $newCredits = floatval($expense->credits_received ?? 0);
            $difference = $newCredits - $oldCredits;

            if ($difference != 0) {
                $oldBalance = $expense->supplier->balance;

                if ($difference > 0) {
                    $expense->supplier->addToBalance($difference);
                } else {
                    $expense->supplier->subtractFromBalance(abs($difference));
                }

                Log::info('🔄 Balance ajustado por modificación de pago', [
                    'expense_id' => $expense->id,
                    'supplier' => $expense->supplier->name,
                    'old_credits' => $oldCredits,
                    'new_credits' => $newCredits,
                    'difference' => $difference,
                    'old_balance' => $oldBalance,
                    'new_balance' => $expense->supplier->fresh()->balance,
                ]);
            }
        }
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

        $creditsToRemove = floatval($expense->credits_received ?? 0);

        if ($creditsToRemove <= 0) {
            return;
        }

        $oldBalance = $expense->supplier->balance;
        $expense->supplier->subtractFromBalance($creditsToRemove);

        Log::info('🔄 Balance revertido por eliminación de pago', [
            'expense_id' => $expense->id,
            'supplier' => $expense->supplier->name,
            'credits_removed' => $creditsToRemove,
            'old_balance' => $oldBalance,
            'new_balance' => $expense->supplier->fresh()->balance,
        ]);
    }
}
