<?php

namespace App\Filament\Resources\AdSubscriptionResource\Pages;

use App\Filament\Resources\AdSubscriptionResource;
use Filament\Resources\Pages\ListRecords;

class ListAdSubscriptions extends ListRecords
{
    protected static string $resource = AdSubscriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
