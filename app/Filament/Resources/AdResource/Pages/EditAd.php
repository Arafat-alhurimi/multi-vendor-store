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
        $data['content_type'] = ($data['click_action'] ?? null) === 'promotion'
            ? 'promotion'
            : (($data['media_type'] ?? null) === 'video' ? 'video' : 'image');

        if (($data['click_action'] ?? null) === 'promotion' && ! empty($data['action_id'])) {
            $data['promotion_id'] = (int) $data['action_id'];
        }

        if (($data['click_action'] ?? null) === 'product' && ! empty($data['action_id'])) {
            $data['product_action_id'] = (int) $data['action_id'];
        }

        if (($data['click_action'] ?? null) === 'store' && ! empty($data['action_id'])) {
            $data['store_action_id'] = (int) $data['action_id'];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['promotion_id'])) {
            $data['click_action'] = 'promotion';
            $data['media_type'] = 'image';
        }

        if (($data['click_action'] ?? null) === 'product' && ! empty($data['product_action_id'])) {
            $data['action_id'] = (string) $data['product_action_id'];
        }

        if (($data['click_action'] ?? null) === 'store' && ! empty($data['store_action_id'])) {
            $data['action_id'] = (string) $data['store_action_id'];
        }

        if (! empty($data['promotion_id'])) {
            $data['action_id'] = (string) $data['promotion_id'];
            $data['media_type'] = 'image';
            $data['click_action'] = 'promotion';
        }

        unset($data['promotion_id']);
        unset($data['content_type']);
        unset($data['product_action_id']);
        unset($data['store_action_id']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
