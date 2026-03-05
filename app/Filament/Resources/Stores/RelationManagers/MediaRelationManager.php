<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';

    protected static ?string $title = 'وسائط المتجر';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->label('اسم الملف')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('file_type')
                    ->label('النوع'),
                Tables\Columns\ImageColumn::make('url')
                    ->label('معاينة الصورة')
                    ->visible(fn ($record): bool => $record?->file_type === 'image'),
                Tables\Columns\TextColumn::make('url')
                    ->label('معاينة الفيديو')
                    ->formatStateUsing(fn (?string $state): string => $state ? '▶ معاينة فيديو' : '-')
                    ->url(fn (?string $state): ?string => $state)
                    ->openUrlInNewTab()
                    ->visible(fn ($record): bool => $record?->file_type === 'video'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('file_type')
                    ->label('نوع الوسائط')
                    ->options([
                        'image' => 'صور',
                        'video' => 'فيديو',
                    ]),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
