<?php

namespace App\Filament\Resources\PricePackageResource\Pages;

use App\Filament\Resources\PricePackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPricePackages extends ListRecords
{
    protected static string $resource = PricePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
