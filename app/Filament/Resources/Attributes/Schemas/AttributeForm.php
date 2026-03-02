<?php

namespace App\Filament\Resources\Attributes\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->required(),
                TextInput::make('name_en')
                    ->label('الاسم (EN)')
                    ->required(),
                Repeater::make('values')
                    ->label('قيم الخاصية')
                    ->relationship()
                    ->schema([
                        TextInput::make('value_ar')
                            ->label('القيمة (عربي)')
                            ->required(),
                        TextInput::make('value_en')
                            ->label('القيمة (EN)')
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]);
    }
}
