<?php

namespace App\Filament\Resources\Subcategories;

use App\Filament\Resources\Subcategories\Pages\ViewSubcategory;
use App\Filament\Resources\Subcategories\Pages\ListSubcategories;
use App\Filament\Resources\Subcategories\Pages\CreateSubcategory;
use App\Filament\Resources\Subcategories\Pages\EditSubcategory;
use App\Models\Subcategory;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use App\Filament\Resources\Subcategories\Tables\SubcategoriesTable;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use BackedEnum;

class SubcategoryResource extends Resource
{
    protected static ?string $model = Subcategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'الفئات الفرعية';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('category_id')
                    ->relationship('category', 'name_ar')
                    ->label('الفئة الرئيسية')
                    ->default(fn () => request()->query('category_id'))
                    ->hidden(fn (?string $operation): bool => $operation === 'create' && filled(request()->query('category_id')))
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->required(),
                TextInput::make('name_en')
                    ->label('الاسم (EN)')
                    ->required(),
                Textarea::make('description_ar')
                    ->label('الوصف (عربي)')
                    ->columnSpanFull(),
                Textarea::make('description_en')
                    ->label('الوصف (EN)')
                    ->columnSpanFull(),
                FileUpload::make('image')
                    ->label('صورة')
                    ->image()
                    ->disk('s3')
                    ->directory('subcategories')
                    ->visibility('public')
                    ->imageAspectRatio('4:3')
                    ->automaticallyCropImagesToAspectRatio()
                    ->automaticallyResizeImagesToWidth(800)
                    ->automaticallyResizeImagesToHeight(600)
                    ->imagePreviewHeight(150),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return SubcategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubcategories::route('/'),
            'create' => CreateSubcategory::route('/create'),
            'view' => ViewSubcategory::route('/{record}'),
            'edit' => EditSubcategory::route('/{record}/edit'),
        ];
    }
}
