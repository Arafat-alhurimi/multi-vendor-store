<?php

namespace App\Filament\Resources\Subcategories\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Subcategories\SubcategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSubcategory extends CreateRecord
{
    protected static string $resource = SubcategoryResource::class;

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        $categoryId = $this->record?->category_id ?? request()->query('category_id');

        if (filled($categoryId)) {
            return CategoryResource::getUrl('view', ['record' => $categoryId]);
        }

        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['order'] = $data['order'] ?? 0;

        return $data;
    }
}
