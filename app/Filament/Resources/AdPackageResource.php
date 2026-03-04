<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdPackageResource\Pages;
use App\Models\AdPackage;
use Illuminate\Database\Eloquent\Model;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdPackageResource extends Resource
{
    protected static ?string $model = AdPackage::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'باقات الإعلانات';

    protected static string | \UnitEnum | null $navigationGroup = 'الإعلانات';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label('اسم الباقة')->required()->maxLength(255),
            TextInput::make('price')->label('السعر')->numeric()->required(),
            TextInput::make('duration_days')->label('مدة الباقة بالأيام')->numeric()->required(),
            TextInput::make('max_images')->label('الحد الأقصى للصور')->numeric()->required(),
            TextInput::make('max_videos')->label('الحد الأقصى للفيديو')->numeric()->required(),
            TextInput::make('max_promotions')->label('الحد الأقصى لنقرات العروض')->numeric()->required(),
            Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الباقة')->searchable(),
                TextColumn::make('price')->label('السعر')->numeric(2)->sortable(),
                TextColumn::make('duration_days')->label('المدة')->sortable(),
                TextColumn::make('max_images')->label('صور')->sortable(),
                TextColumn::make('max_videos')->label('فيديو')->sortable(),
                TextColumn::make('max_promotions')->label('عروض')->sortable(),
                IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdPackages::route('/'),
            'create' => Pages\CreateAdPackage::route('/create'),
            'edit' => Pages\EditAdPackage::route('/{record}/edit'),
        ];
    }
}
