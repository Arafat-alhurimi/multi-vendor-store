<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $oldImage = $this->getRecord()->image;
        $newImage = $data['image'] ?? null;

        if (blank($newImage) && filled($oldImage)) {
            Storage::disk('s3')->delete($oldImage);
        } elseif (filled($newImage) && filled($oldImage) && $newImage !== $oldImage) {
            Storage::disk('s3')->delete($oldImage);
        }

        return $data;
    }
}
