<?php

namespace App\Filament\Resources\SupplierBalanceAdjustmentResource\Pages;

use App\Filament\Resources\SupplierBalanceAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierBalanceAdjustments extends ListRecords
{
    protected static string $resource = SupplierBalanceAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
