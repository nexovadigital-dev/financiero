<?php

namespace App\Filament\Resources\OperationalExpenseResource\Pages;

use App\Filament\Resources\OperationalExpenseResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationalExpense extends CreateRecord
{
    protected static string $resource = OperationalExpenseResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Gasto operativo registrado correctamente';
    }

    public function getTitle(): string
    {
        return 'Registrar Gasto Operativo';
    }
}
