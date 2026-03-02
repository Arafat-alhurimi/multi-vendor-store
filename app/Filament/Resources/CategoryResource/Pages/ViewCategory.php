<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Subcategories\SubcategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;


class ViewCategory extends ViewRecord
{
    
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('إضافة قسم فرعي')
                ->model(\App\Models\Subcategory::class)
                ->form([
                    Hidden::make('category_id')
                        ->default($this->getRecord()->id),
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
                    TextInput::make('order')
                        ->label('ترتيب')
                        ->numeric()
                        ->default(0),
                ]),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name_ar')
                    ->label('الاسم (عربي)'),
                TextEntry::make('name_en')
                    ->label('الاسم (EN)'),
                TextEntry::make('description_ar')
                    ->label('الوصف (عربي)')
                    ->columnSpanFull(),
                TextEntry::make('description_en')
                    ->label('الوصف (EN)')
                    ->columnSpanFull(),
                ImageEntry::make('image')
                    ->disk('s3')
                    ->label('صورة'),
                TextEntry::make('is_active')
                    ->label('نشط')
                    ->formatStateUsing(fn (string $state): string => $state ? 'نعم' : 'لا'),
                TextEntry::make('order')
                    ->label('ترتيب'),
            ])
            ->columns(2);
    }
}
