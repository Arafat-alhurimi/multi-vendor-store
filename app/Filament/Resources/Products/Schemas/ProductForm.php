<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('ProductTabs')
                    ->tabs([
                        Tab::make('البيانات الأساسية')
                            ->schema([
                                Select::make('store_id')
                                    ->label('المتجر')
                                    ->relationship('store', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required(),
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
                                    ->label('الوصف (عربي)')
                                    ->columnSpanFull(),
                                Textarea::make('description_en')
                                    ->label('الوصف (EN)')
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('السعر والمخزون')
                            ->schema([
                                TextInput::make('base_price')
                                    ->label('السعر الأساسي')
                                    ->required()
                                    ->numeric(),
                                TextInput::make('stock')
                                    ->label('المخزون')
                                    ->required()
                                    ->numeric()
                                    ->default(0),
                                Toggle::make('is_featured')
                                    ->label('مميز؟')
                                    ->default(false),
                                Toggle::make('is_active')
                                    ->label('نشط')
                                    ->default(true),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
