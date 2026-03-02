<?php

namespace App\Filament\Resources\Subcategories\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SubcategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name_ar')
                    ->label('القسم الرئيسي')
                    ->required(),
                TextInput::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->required(),
                TextInput::make('name_en')
                    ->label('الاسم (EN)')
                    ->required(),
                Textarea::make('description_ar')
                    ->label('الوصف (عربي)')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('description_en')
                    ->label('الوصف (EN)')
                    ->default(null)
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
                    ->required()
                    ->default(true),
                TextInput::make('order')
                    ->label('ترتيب')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
