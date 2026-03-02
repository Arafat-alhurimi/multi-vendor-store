<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;

class SubcategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'subcategories';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Hidden::make('category_id')
                    ->default(fn () => $this->ownerRecord->id),

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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ar')
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->disk('s3')
                    ->label('صورة')
                    ->circular()
                    ->imageHeight(60),

                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name_en')
                    ->label('الاسم (EN)')
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),

                Tables\Columns\TextColumn::make('order')
                    ->label('ترتيب')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('order');
    }
}