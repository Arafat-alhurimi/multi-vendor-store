<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\Subcategories\SubcategoryResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewCategory extends ViewRecord
{
    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('deactivate')
                ->label('إلغاء التفعيل')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => (bool) $this->getRecord()->is_active)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->getRecord()->update(['is_active' => false]);

                    Notification::make()
                        ->title('تم إلغاء تفعيل الفئة الرئيسية')
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('صورة الفئة الرئيسية')
                    ->schema([
                        ImageEntry::make('image')
                            ->disk('s3')
                            ->label('الصورة')
                            ->height(220)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('بيانات الفئة الرئيسية')
                    ->schema([
                        TextEntry::make('name_ar')
                            ->label('الاسم (عربي)')
                            ->placeholder('-'),
                        TextEntry::make('name_en')
                            ->label('الاسم (EN)')
                            ->placeholder('-'),
                        TextEntry::make('is_active')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشطة' : 'غير نشطة')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('created_at')
                            ->label('تاريخ الإضافة')
                            ->since()
                            ->placeholder('-'),
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
                        TextEntry::make('subcategories_count')
                            ->label('عدد الفئات الفرعية')
                            ->state(fn (): int => (int) $this->getRecord()->subcategories()->count()),
                        TextEntry::make('stores_count')
                            ->label('عدد المتاجر التي تتضمن هذه الفئة')
                            ->state(fn (): int => (int) $this->getRecord()->stores()->count()),
                        TextEntry::make('products_total_count')
                            ->label('إجمالي المنتجات ضمن كل الفئات الفرعية')
                            ->state(fn (): int => Product::query()
                                ->whereHas('subcategory', fn ($query) => $query->where('category_id', $this->getRecord()->id))
                                ->count()),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Tabs::make('تفاصيل مرتبطة')
                    ->tabs([
                        Tab::make('الفئات الفرعية')
                            ->badge(fn (): int => (int) $this->getRecord()->subcategories()->count())
                            ->schema([
                                TextEntry::make('create_subcategory_action')
                                    ->label('')
                                    ->state('إضافة فئة فرعية')
                                    ->icon('heroicon-o-plus')
                                    ->color('primary')
                                    ->url(fn (): string => SubcategoryResource::getUrl('create', [
                                        'category_id' => $this->getRecord()->id,
                                    ])),
                                RepeatableEntry::make('subcategories')
                                    ->label('الفئات الفرعية')
                                    ->placeholder('لا توجد فئات فرعية')
                                    ->schema([
                                        ImageEntry::make('image')
                                            ->disk('s3')
                                            ->label('الصورة'),
                                        TextEntry::make('name_ar')->label('الاسم (عربي)')->placeholder('-'),
                                        TextEntry::make('name_en')->label('الاسم (EN)')->placeholder('-'),
                                        TextEntry::make('is_active')
                                            ->label('الحالة')
                                            ->badge()
                                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشطة' : 'غير نشطة')
                                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                                        TextEntry::make('products_count')
                                            ->label('عدد المنتجات')
                                            ->state(fn ($record): int => (int) $record->products()->count()),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('المتاجر')
                            ->badge(fn (): int => (int) $this->getRecord()->stores()->count())
                            ->schema([
                                RepeatableEntry::make('stores')
                                    ->label('المتاجر التي تتضمن هذه الفئة')
                                    ->placeholder('لا توجد متاجر مرتبطة')
                                    ->schema([
                                        ImageEntry::make('logo')
                                            ->disk('s3')
                                            ->label('الشعار'),
                                        TextEntry::make('name')->label('اسم المتجر')->placeholder('-'),
                                        TextEntry::make('user.name')->label('البائع')->placeholder('-'),
                                        TextEntry::make('city')->label('المدينة')->placeholder('-'),
                                        TextEntry::make('pivot.created_at')
                                            ->label('تاريخ إضافة الفئة للمتجر')
                                            ->since()
                                            ->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
