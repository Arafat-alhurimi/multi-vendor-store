<?php

namespace App\Filament\Resources\StorePromotions\Pages;

use App\Filament\Resources\StorePromotions\StorePromotionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStorePromotion extends ViewRecord
{
    protected static string $resource = StorePromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
