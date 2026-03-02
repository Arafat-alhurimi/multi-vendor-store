<?php

namespace App\Filament\Resources\AdPackageResource\Pages;

use App\Filament\Resources\AdPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdPackages extends ListRecords
{
    protected static string $resource = AdPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
