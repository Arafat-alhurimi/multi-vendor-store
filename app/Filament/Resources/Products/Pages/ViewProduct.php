<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name_ar')->label('الاسم (عربي)'),
                TextEntry::make('name_en')->label('الاسم (EN)'),
                TextEntry::make('store.name')->label('المتجر'),
                TextEntry::make('subcategory.name_ar')->label('القسم الفرعي'),
                TextEntry::make('base_price')->label('السعر الأساسي'),
                TextEntry::make('stock')->label('المخزون'),
                TextEntry::make('is_featured')
                    ->label('مميز')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نعم' : 'لا'),
                TextEntry::make('is_active')
                    ->label('نشط')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'نعم' : 'لا'),
                TextEntry::make('variants_count')
                    ->label('عدد المنتجات الفرعية')
                    ->state(fn () => (int) $this->getRecord()->variants()->count()),
                TextEntry::make('comments_count')
                    ->label('عدد التعليقات')
                    ->state(fn () => (int) $this->getRecord()->comments()->count()),
                TextEntry::make('avg_rating')
                    ->label('متوسط التقييم')
                    ->state(fn () => number_format((float) ($this->getRecord()->ratings()->avg('value') ?? 0), 2)),
                TextEntry::make('product_attributes')
                    ->label('الخصائص المرتبطة')
                    ->state(function (): array {
                        return $this->getRecord()
                            ->variants()
                            ->with('attributeValues.attribute')
                            ->get()
                            ->flatMap(fn ($variant) => $variant->attributeValues->pluck('attribute.name_ar'))
                            ->filter()
                            ->unique()
                            ->values()
                            ->all();
                    })
                    ->listWithLineBreaks()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
