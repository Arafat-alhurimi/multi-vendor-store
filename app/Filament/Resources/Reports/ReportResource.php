<?php

namespace App\Filament\Resources\Reports;

use App\Filament\Resources\Reports\Tables\ReportsTable;
use App\Models\Report;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'البلاغات';

    protected static string | \UnitEnum | null $navigationGroup = 'أخرى';

    protected static ?int $navigationSort = 999;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return ReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Reports\Pages\ListReports::route('/'),
            'view' => \App\Filament\Resources\Reports\Pages\ViewReport::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function navigationBadge(): ?string
    {
        $count = static::$model::query()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function navigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
