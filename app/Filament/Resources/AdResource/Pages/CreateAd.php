<?php

namespace App\Filament\Resources\AdResource\Pages;

use App\Filament\Resources\AdResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAd extends CreateRecord
{
    protected static string $resource = AdResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['click_action'] ?? null) === 'promotion' && ! empty($data['promotion_id'])) {
            $data['action_id'] = (string) $data['promotion_id'];
        }

        unset($data['promotion_id']);

        return $data;
    }
}
