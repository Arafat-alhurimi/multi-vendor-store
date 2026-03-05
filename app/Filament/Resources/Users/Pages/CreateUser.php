<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('حفظ');
    }

    protected function getRedirectUrl(): string
    {
        $record = $this->getRecord();

        if ($record?->role === 'vendor') {
            return StoreResource::getUrl('create', ['user_id' => $record->id]);
        }

        return UserResource::getUrl('index');
    }
}
