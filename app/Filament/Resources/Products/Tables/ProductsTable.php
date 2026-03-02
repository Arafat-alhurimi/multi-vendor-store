<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),
                TextColumn::make('store.name')
                    ->label('المتجر')
                    ->searchable(),
                TextColumn::make('subcategory.name_ar')
                    ->label('القسم الفرعي')
                    ->searchable(),
                TextColumn::make('base_price')
                    ->label('السعر')
                    ->money('SAR')
                    ->sortable(),
                TextColumn::make('stock')
                    ->label('المخزون')
                    ->sortable(),
                IconColumn::make('is_featured')
                    ->boolean()
                    ->label('مميز'),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label('المتجر')
                    ->relationship('store', 'name'),
                SelectFilter::make('subcategory_id')
                    ->label('القسم الفرعي')
                    ->relationship('subcategory', 'name_ar'),
                TernaryFilter::make('is_active')
                    ->label('الحالة'),
                TernaryFilter::make('is_featured')
                    ->label('مميز/غير مميز'),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
