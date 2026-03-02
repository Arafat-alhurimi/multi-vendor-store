<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\DeleteAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'تعليقات المنتج';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('body')
            ->columns([
                Tables\Columns\TextColumn::make('body')
                    ->label('التعليق')
                    ->wrap()
                    ->limit(80),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('المستخدم'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->headerActions([])
            ->actions([
                DeleteAction::make(),
            ]);
    }
}
