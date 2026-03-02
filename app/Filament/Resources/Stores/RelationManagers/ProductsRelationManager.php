<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    protected static ?string $title = 'منتجات المتجر';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('subcategory_id')
                    ->label('القسم الفرعي')
                    ->relationship('subcategory', 'name_ar')
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
                    ->label('الوصف (عربي)'),
                Textarea::make('description_en')
                    ->label('الوصف (EN)'),
                TextInput::make('base_price')
                    ->label('السعر الأساسي')
                    ->numeric()
                    ->required(),
                TextInput::make('stock')
                    ->label('المخزون')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_featured')
                    ->label('مميز؟')
                    ->default(false),
                Toggle::make('is_active')
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_ar')
            ->recordUrl(fn ($record): string => ProductResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),
                Tables\Columns\TextColumn::make('subcategory.name_ar')
                    ->label('القسم الفرعي'),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('السعر')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('المخزون'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->boolean()
                    ->label('مميز'),
            ])
            ->headerActions([])
            ->actions([
                Action::make('viewProduct')
                    ->label('عرض المنتج')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): string => ProductResource::getUrl('view', ['record' => $record])),
                DeleteAction::make(),
            ]);
    }
}
