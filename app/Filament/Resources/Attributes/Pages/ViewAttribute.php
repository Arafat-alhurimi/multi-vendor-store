<?php

namespace App\Filament\Resources\Attributes\Pages;

use App\Filament\Resources\Attributes\AttributeResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewAttribute extends ViewRecord
{
    protected static string $resource = AttributeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name_ar')->label('الاسم (عربي)'),
                TextEntry::make('name_en')->label('الاسم (EN)'),
                TextEntry::make('values.value_ar')
                    ->label('القيم')
                    ->listWithLineBreaks()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
