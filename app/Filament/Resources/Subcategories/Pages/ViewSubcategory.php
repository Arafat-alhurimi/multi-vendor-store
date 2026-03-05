<?php

namespace App\Filament\Resources\Subcategories\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Subcategories\SubcategoryResource;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewSubcategory extends ViewRecord
{
    protected static string $resource = SubcategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('backToCategory')
                ->label('العودة للفئة الرئيسية')
                ->icon('heroicon-o-arrow-right')
                ->color('gray')
                ->url(fn (): string => CategoryResource::getUrl('view', ['record' => $this->getRecord()->category_id])),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('صورة الفئة الفرعية')
                    ->schema([
                        ImageEntry::make('image')
                            ->disk('s3')
                            ->label('الصورة')
                            ->height(220)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('بيانات الفئة الفرعية')
                    ->schema([
                        TextEntry::make('name_ar')
                            ->label('الاسم (عربي)')
                            ->placeholder('-'),
                        TextEntry::make('name_en')
                            ->label('الاسم (EN)')
                            ->placeholder('-'),
                        TextEntry::make('category.name_ar')
                            ->label('الفئة الرئيسية')
                            ->placeholder('-'),
                        TextEntry::make('is_active')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشطة' : 'غير نشطة')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('description_ar')
                            ->label('الوصف (عربي)')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('description_en')
                            ->label('الوصف (EN)')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('تفاصيل إضافية')
                    ->schema([
                        TextEntry::make('products_count')
                            ->label('عدد المنتجات')
                            ->state(fn (): int => (int) $this->getRecord()->products()->count()),
                        TextEntry::make('created_at')
                            ->label('تاريخ الإضافة')
                            ->since()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('آخر تحديث')
                            ->since()
                            ->placeholder('-'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}