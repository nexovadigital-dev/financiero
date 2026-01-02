<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Validar antes de crear que el proveedor tenga balance suficiente
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Verificar si es una venta con créditos de servidor
        if (!isset($data['payment_method_id']) || empty($data['supplier_id'])) {
            return $data;
        }

        // Si está marcado "sin proveedor", no validar balance
        if (!empty($data['without_supplier'])) {
            return $data;
        }

        $paymentMethod = PaymentMethod::find($data['payment_method_id']);
        if (!$paymentMethod || !$paymentMethod->isServerCredits()) {
            return $data;
        }

        // Es una venta de créditos con proveedor - validar balance
        $supplier = Supplier::find($data['supplier_id']);
        if (!$supplier) {
            throw ValidationException::withMessages([
                'supplier_id' => 'El proveedor seleccionado no existe.',
            ]);
        }

        // Calcular el costo base total de los items
        $totalBaseCost = 0;
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $basePrice = floatval($item['base_price'] ?? 0);
                $qty = intval($item['quantity'] ?? 1);
                $totalBaseCost += ($basePrice * $qty);
            }
        }

        // Validar que el proveedor tenga balance suficiente
        if ($supplier->balance < $totalBaseCost) {
            Notification::make()
                ->danger()
                ->title('❌ Balance Insuficiente')
                ->body(sprintf(
                    'El proveedor "%s" tiene un balance de $%.2f USD, pero esta venta requiere $%.2f USD. Por favor, registre un pago al proveedor primero.',
                    $supplier->name,
                    $supplier->balance,
                    $totalBaseCost
                ))
                ->persistent()
                ->send();

            throw ValidationException::withMessages([
                'supplier_id' => sprintf(
                    'Balance insuficiente. Disponible: $%.2f, Requerido: $%.2f',
                    $supplier->balance,
                    $totalBaseCost
                ),
            ]);
        }

        return $data;
    }
}
