<?php

namespace App\Filament\Resources\AdSubscriptionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AdsRelationManager extends RelationManager
{
    protected static string $relationship = 'ads';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('ID'),
                Tables\Columns\TextColumn::make('media_type')->label('نوع الوسائط'),
                Tables\Columns\TextColumn::make('click_action')->label('إجراء النقر'),
                Tables\Columns\TextColumn::make('starts_at')->label('يبدأ')->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('ends_at')->label('ينتهي')->dateTime('Y-m-d H:i'),
                Tables\Columns\IconColumn::make('is_active')->label('نشط')->boolean(),
            ]);
    }
}
