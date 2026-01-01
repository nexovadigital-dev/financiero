<?php

namespace App\Filament\Resources\PricePackageResource\Pages;

use App\Filament\Resources\PricePackageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPricePackage extends EditRecord
{
    protected static string $resource = PricePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
