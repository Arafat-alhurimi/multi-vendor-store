<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;
        $now = now();

        $data['is_active'] = (! $startsAt || $startsAt->lte($now))
            && (! $endsAt || $endsAt->gte($now));

        return $data;
    }
}
