<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('pendingApprovals')
                ->label('الحسابات التي تحتاج موافقة')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->url(UserResource::getUrl('pending')),
        ];
    }
}
