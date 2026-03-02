<?php

namespace App\Filament\Resources\AdPackageResource\Pages;

use App\Filament\Resources\AdPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdPackage extends EditRecord
{
    protected static string $resource = AdPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
