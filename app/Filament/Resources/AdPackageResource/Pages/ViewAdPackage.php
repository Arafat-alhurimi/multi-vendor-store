<?php

namespace App\Filament\Resources\AdPackageResource\Pages;

use App\Filament\Resources\AdPackageResource;
use App\Models\AdPackage;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewAdPackage extends ViewRecord
{
    protected static string $resource = AdPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        /** @var AdPackage $package */
        $package = $this->getRecord();

        return $schema
            ->schema([
                Section::make('معلومات الباقة')
                    ->schema([
                        TextEntry::make('name')->label('اسم الباقة')->weight('bold'),
                        TextEntry::make('price')->label('السعر')->numeric(2),
                        TextEntry::make('duration_days')->label('مدة الباقة (يوم)'),
                        TextEntry::make('is_active')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشطة' : 'غير نشطة')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('created_at')->label('تاريخ الإضافة')->dateTime('Y-m-d H:i'),
                        TextEntry::make('updated_at')->label('آخر تحديث')->dateTime('Y-m-d H:i'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('حدود الباقة')
                    ->schema([
                        TextEntry::make('max_images')->label('الحد الأعلى للصور')->badge()->color('info'),
                        TextEntry::make('max_videos')->label('الحد الأعلى للفيديو')->badge()->color('info'),
                        TextEntry::make('max_promotions')->label('الحد الأعلى للعروض')->badge()->color('info'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                Section::make('إحصائيات الباقة')
                    ->schema([
                        TextEntry::make('stats_total_subscriptions')
                            ->label('إجمالي الاشتراكات')
                            ->state(fn (): int => (int) $package->vendorSubscriptions()->count())
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('stats_pending_requests')
                            ->label('طلبات الاشتراك')
                            ->state(fn (): int => (int) $package->vendorSubscriptions()->where('status', 'pending')->count())
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('stats_active_subscriptions')
                            ->label('اشتراكات نشطة')
                            ->state(fn (): int => (int) $package->vendorSubscriptions()->where('status', 'active')->count())
                            ->badge()
                            ->color('success'),
                        TextEntry::make('stats_expired_subscriptions')
                            ->label('اشتراكات منتهية')
                            ->state(fn (): int => (int) $package->vendorSubscriptions()->where('status', 'expired')->count())
                            ->badge()
                            ->color('danger'),
                    ])
                    ->columns(4)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
