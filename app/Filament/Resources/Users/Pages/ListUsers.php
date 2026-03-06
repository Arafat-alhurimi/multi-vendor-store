<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Widgets\UsersStatsOverview;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createCustomer')
                ->label('إضافة عميل')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->url(UserResource::getUrl('create', ['role' => 'customer'])),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()->where('role', 'customer');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UsersStatsOverview::class,
        ];
    }
}
