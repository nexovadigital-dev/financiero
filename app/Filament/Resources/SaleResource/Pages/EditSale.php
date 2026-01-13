<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditSale extends EditRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];

        // BOTÓN REEMBOLSAR - Solo para ventas de créditos no reembolsadas
        if ($this->record->canBeRefunded()) {
            $actions[] = Actions\Action::make('refund')
                ->label('Reembolsar Transacción')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('¿Reembolsar esta venta de créditos?')
                ->modalDescription(function () {
                    $sale = $this->record;
                    return 'Se acreditará $' . number_format($sale->amount_usd, 2) . ' USD de vuelta al proveedor "' .
                        $sale->supplier->name . '". Esta acción NO se puede deshacer.';
                })
                ->modalSubmitActionLabel('Sí, Reembolsar')
                ->action(function () {
                    $sale = $this->record;
                    if ($sale->refund()) {
                        Notification::make()
                            ->success()
                            ->title('Venta Reembolsada')
                            ->body('Se acreditó $' . number_format($sale->amount_usd, 2) . ' USD al proveedor.')
                            ->send();

                        return redirect()->route('filament.admin.resources.sales.index');
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error al Reembolsar')
                            ->body('Esta venta no puede ser reembolsada.')
                            ->send();
                    }
                });
        }

        // BOTÓN ANULAR - Para todas las ventas que no estén canceladas
        if ($this->record->status !== 'cancelled') {
            $actions[] = Actions\Action::make('cancel')
                ->label('Anular Venta')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Anular Venta')
                ->modalDescription(function () {
                    $sale = $this->record;
                    $message = "⚠️ ADVERTENCIA: Esta acción:\n\n" .
                        "• Eliminará la ganancia de esta venta ($" . number_format($sale->amount_usd, 2) . " USD) de los reportes\n";

                    if ($sale->supplier_id && $sale->supplier) {
                        $message .= "• Devolverá el crédito al proveedor {$sale->supplier->name}\n";
                    }

                    $message .= "• Marcará la venta como Cancelada\n" .
                        "• Esta acción NO se puede revertir\n\n" .
                        "¿Está seguro que desea anular esta venta?";

                    return $message;
                })
                ->modalSubmitActionLabel('Sí, anular venta')
                ->action(function () {
                    $sale = $this->record;

                    // Calcular el monto a devolver (precio base)
                    $totalBaseCost = $sale->items->sum(function ($item) {
                        return ($item->base_price ?? 0) * $item->quantity;
                    });
                    $amountToRefund = $totalBaseCost > 0 ? $totalBaseCost : $sale->amount_usd;

                    // Si tiene proveedor, devolver el crédito
                    if ($sale->supplier_id && $sale->supplier) {
                        $sale->supplier->addToBalance(
                            amount: $amountToRefund,
                            type: 'sale_refund',
                            description: "Anulación Venta #{$sale->id} - Cliente: {$sale->client->name}",
                            reference: $sale
                        );

                        \Log::info('↩️ Crédito devuelto por anulación de venta', [
                            'sale_id' => $sale->id,
                            'supplier' => $sale->supplier->name,
                            'amount_refunded' => $amountToRefund,
                            'user_id' => auth()->id(),
                        ]);
                    }

                    // Marcar venta como cancelada
                    $sale->update([
                        'status' => 'cancelled',
                        'refunded_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Venta Anulada')
                        ->body("La venta #{$sale->id} ha sido anulada exitosamente." .
                            ($sale->supplier_id ? " Se devolvió el crédito al proveedor." : ""))
                        ->send();

                    return redirect()->route('filament.admin.resources.sales.index');
                });
        }

        // BOTÓN ELIMINAR - Solo para ventas NO de créditos o sin proveedor
        if (!$this->record->isProviderCredit() || $this->record->without_supplier) {
            $actions[] = Actions\DeleteAction::make();
        }

        return $actions;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Mostrar UNA SOLA notificación según el estado (evita bloqueo de UI)
        if ($this->record->isRefunded()) {
            Notification::make()
                ->danger()
                ->title('⛔ Venta REEMBOLSADA - Bloqueada')
                ->body('Esta venta está REEMBOLSADA y NO puede editarse bajo ninguna circunstancia.')
                ->send(); // No persistent para no bloquear
        }
        elseif ($this->record->isProviderCredit()) {
            Notification::make()
                ->warning()
                ->title('📋 Venta de Créditos - Solo Lectura')
                ->body('Las ventas de créditos NO pueden editarse para evitar descuadres contables. Use "Reembolsar" si necesita cancelar.')
                ->send(); // No persistent para no bloquear
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        // Bloquear edición de ventas reembolsadas (PRIORIDAD MÁXIMA)
        if ($this->record->isRefunded()) {
            Notification::make()
                ->danger()
                ->title('Edición BLOQUEADA')
                ->body('Las ventas REEMBOLSADAS NO pueden modificarse.')
                ->send();

            $this->halt();
        }

        // Bloquear edición de ventas de créditos activas
        if ($this->record->isProviderCredit()) {
            Notification::make()
                ->danger()
                ->title('Edición Bloqueada')
                ->body('Las ventas de créditos NO pueden modificarse después de creadas.')
                ->send();

            $this->halt();
        }
    }
}
