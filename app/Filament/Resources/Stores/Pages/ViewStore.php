<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewStore extends ViewRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextEntry::make('name')
                    ->label('اسم المتجر'),
                TextEntry::make('user.name')
                    ->label('البائع'),
                TextEntry::make('city')
                    ->label('المدينة'),
                TextEntry::make('address')
                    ->label('العنوان'),
                TextEntry::make('description')
                    ->label('الوصف')
                    ->columnSpanFull(),
                ImageEntry::make('logo')
                    ->disk('s3')
                    ->label('شعار المتجر'),
                TextEntry::make('categories.name_ar')
                    ->label('الأقسام')
                    ->listWithLineBreaks()
                    ->columnSpanFull(),
                TextEntry::make('products_count')
                    ->label('عدد المنتجات')
                    ->state(fn () => (int) $this->getRecord()->products()->count()),
                TextEntry::make('favorites_count')
                    ->label('عدد الإضافات للمفضلة')
                    ->state(fn () => (int) $this->getRecord()->favorites()->count()),
                TextEntry::make('avg_rating')
                    ->label('متوسط التقييم')
                    ->state(fn () => number_format((float) ($this->getRecord()->ratings()->avg('value') ?? 0), 2)),
                TextEntry::make('is_active')
                    ->label('نشط')
                    ->formatStateUsing(fn (string $state): string => $state ? 'نعم' : 'لا'),
                TextEntry::make('user.vendorFinancialDetail.kuraimi_account_number')
                    ->label('رقم حساب الكريمي')
                    ->placeholder('-'),
                TextEntry::make('user.vendorFinancialDetail.kuraimi_account_name')
                    ->label('اسم حساب الكريمي')
                    ->placeholder('-'),
                TextEntry::make('user.vendorFinancialDetail.jeeb_id')
                    ->label('معرّف جيب')
                    ->placeholder('-'),
                TextEntry::make('user.vendorFinancialDetail.jeeb_name')
                    ->label('اسم حساب جيب')
                    ->placeholder('-'),
                TextEntry::make('user.vendorFinancialDetail.total_commission_owed')
                    ->label('إجمالي العمولات المستحقة')
                    ->placeholder('-'),
            ])
            ->columns(2);
    }
}
