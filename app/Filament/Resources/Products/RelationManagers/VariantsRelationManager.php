<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'النسخ الفرعية';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('sku')
                    ->label('SKU')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('price')
                    ->label('السعر')
                    ->numeric(),
                TextInput::make('stock')
                    ->label('المخزون')
                    ->required()
                    ->numeric()
                    ->default(0),
                FileUpload::make('image')
                    ->label('صورة النسخة')
                    ->image()
                    ->disk('s3')
                    ->directory('product-variants')
                    ->visibility('public')
                    ->imageAspectRatio('1:1')
                    ->automaticallyCropImagesToAspectRatio(),
                Select::make('attributeValues')
                    ->label('قيم الخصائص')
                    ->relationship('attributeValues', 'value_ar')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->attribute->name_ar . ': ' . $record->value_ar)
                    ->multiple()
                    ->preload()
                    ->searchable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->money('SAR'),
                Tables\Columns\TextColumn::make('stock')
                    ->label('المخزون'),
                Tables\Columns\TextColumn::make('attributeValues.value_ar')
                    ->label('القيم')
                    ->listWithLineBreaks(),
            ])
            ->headerActions([])
            ->actions([
                ViewAction::make(),
                DeleteAction::make(),
            ]);
    }
}
