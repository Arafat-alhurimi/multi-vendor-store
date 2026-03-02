<?php

namespace App\Filament\Resources\AdResource\Pages;

use App\Filament\Resources\AdResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAd extends EditRecord
{
    protected static string $resource = AdResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['click_action'] ?? null) === 'promotion' && ! empty($data['action_id'])) {
            $data['promotion_id'] = (int) $data['action_id'];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['click_action'] ?? null) === 'promotion' && ! empty($data['promotion_id'])) {
            $data['action_id'] = (string) $data['promotion_id'];
        }

        unset($data['promotion_id']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
