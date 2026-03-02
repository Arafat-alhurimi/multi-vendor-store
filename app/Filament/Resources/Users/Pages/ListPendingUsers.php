<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPendingUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'حسابات بانتظار الموافقة';

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationLabel = 'طلبات الموافقة';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | \UnitEnum | null $navigationGroup = 'الحسابات';

    protected static ?int $navigationSort = 2;

    public function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('role', 'vendor')
            ->where('is_active', false);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
