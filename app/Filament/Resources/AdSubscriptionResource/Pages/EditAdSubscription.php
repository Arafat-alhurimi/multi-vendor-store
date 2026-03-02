<?php

namespace App\Filament\Resources\AdSubscriptionResource\Pages;

use App\Filament\Resources\AdSubscriptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdSubscription extends EditRecord
{
    protected static string $resource = AdSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
