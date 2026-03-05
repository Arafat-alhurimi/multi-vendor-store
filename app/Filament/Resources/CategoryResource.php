<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $modelLabel = 'فئة رئيسية';

    protected static ?string $pluralModelLabel = 'الفئات الرئيسية';

    protected static ?string $navigationLabel = 'الفئات الرئيسية';

    protected static string | \UnitEnum | null $navigationGroup = 'إدارة التجارة';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->required(),

                TextInput::make('name_en')
                    ->label('الاسم (EN)')
                    ->required(),

                Textarea::make('description_ar')
                    ->label('الوصف (عربي)'),

                Textarea::make('description_en')
                    ->label('الوصف (EN)'),

                FileUpload::make('image')
                    ->label('صورة')
                    ->image()
                    ->disk('s3')
                    ->directory('categories')
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
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->disk('s3')
                    ->label('صورة')
                    ->circular()
                    ->imageHeight(60),

                TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),

                TextColumn::make('name_en')
                    ->label('الاسم (EN)')
                    ->searchable(),

                TextColumn::make('subcategories_count')
                    ->label('عدد الفئات الفرعية')
                    ->counts('subcategories')
                    ->sortable(),

                TextColumn::make('stores_count')
                    ->label('عدد المتاجر ضمن هذه الفئة')
                    ->counts('stores')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('حالة الفئة')
                    ->trueLabel('نشطة')
                    ->falseLabel('غير نشطة')
                    ->placeholder('الكل'),
                TernaryFilter::make('has_subcategories')
                    ->label('الفئات الفرعية')
                    ->trueLabel('تحتوي فئات فرعية')
                    ->falseLabel('بدون فئات فرعية')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('subcategories'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('subcategories'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_stores')
                    ->label('المتاجر')
                    ->trueLabel('مرتبطة بمتاجر')
                    ->falseLabel('غير مرتبطة بمتاجر')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('stores'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('stores'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->defaultSort('order')
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'view' => Pages\ViewCategory::route('/{record}'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
