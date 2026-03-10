<?php

namespace App\Filament\Resources\AdPackageResource\Pages;

use App\Filament\Resources\AdPackageResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateAdPackage extends CreateRecord
{
    protected static string $resource = AdPackageResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('إضافة باقة');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
