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
        $sale = $this->record;

        return [
            // BOTÓN REEMBOLSAR - Solo para ventas de créditos no reembolsadas
            Actions\Action::make('refund')
                ->label('Reembolsar Transacción')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('¿Reembolsar esta venta de créditos?')
                ->modalDescription(fn () =>
                    'Se acreditará $' . number_format($sale->amount_usd, 2) . ' USD de vuelta al proveedor "' .
                    $sale->supplier->name . '". Esta acción NO se puede deshacer.'
                )
                ->modalSubmitActionLabel('Sí, Reembolsar')
                ->action(function () use ($sale) {
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
                })
                ->visible(fn () => $sale->canBeRefunded()),

            // BOTÓN ELIMINAR - Solo para ventas NO de créditos o sin proveedor
            Actions\DeleteAction::make()
                ->visible(fn () => !$sale->isProviderCredit() || $sale->without_supplier),
        ];
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
