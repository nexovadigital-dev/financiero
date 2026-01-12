<?php

namespace App\Filament\Resources\OperationalExpenseResource\Pages;

use App\Filament\Resources\OperationalExpenseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOperationalExpense extends EditRecord
{
    protected static string $resource = OperationalExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Eliminar Gasto')
                ->modalDescription('¿Está seguro que desea eliminar este gasto operativo?')
                ->modalSubmitActionLabel('Sí, eliminar'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Gasto operativo actualizado correctamente';
    }

    public function getTitle(): string
    {
        return 'Editar Gasto Operativo';
    }
}
