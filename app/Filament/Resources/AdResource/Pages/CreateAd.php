<?php

namespace App\Filament\Resources\AdResource\Pages;

use App\Filament\Resources\AdResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateAd extends CreateRecord
{
    protected static string $resource = AdResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'إضافة محتوى';
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('إضافة محتوى');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
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
}
