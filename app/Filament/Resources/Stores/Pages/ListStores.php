<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Stores\Widgets\StoresStatsOverview;
use App\Filament\Resources\VendorOnboardingResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListStores extends ListRecords
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createStore')
                ->label('إنشاء متجر')
                ->icon('heroicon-o-building-storefront')
                ->color('success')
                ->url(VendorOnboardingResource::getUrl('create')),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StoresStatsOverview::class,
        ];
    }
}
