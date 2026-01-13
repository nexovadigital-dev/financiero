<?php

namespace App\Filament\Resources\SupplierCreditTransactionResource\Pages;

use App\Filament\Resources\SupplierCreditTransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierCreditTransactions extends ListRecords
{
    protected static string $resource = SupplierCreditTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
