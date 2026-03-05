<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Subcategories\SubcategoryResource;
use App\Filament\Resources\CategoryResource\Widgets\CategoriesStatsOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCategories extends ListRecords
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('goToSubcategories')
                ->label('عرض كل الفئات الفرعية')
                ->icon('heroicon-o-squares-2x2')
                ->url(SubcategoryResource::getUrl('index')),
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            CategoriesStatsOverview::class,
        ];
    }
}
