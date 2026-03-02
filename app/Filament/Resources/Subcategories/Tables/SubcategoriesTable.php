<?php

namespace App\Filament\Resources\Subcategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubcategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name_ar')
                    ->label('القسم الرئيسي')
                    ->searchable(),
                TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),
                TextColumn::make('name_en')
                    ->label('الاسم (EN)')
                    ->searchable(),
                ImageColumn::make('image')
                    ->disk('s3')
                    ->label('صورة')
                    ->circular()
                    ->imageHeight(60),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),
                TextColumn::make('order')
                    ->label('ترتيب')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
