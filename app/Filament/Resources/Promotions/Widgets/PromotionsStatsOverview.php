<?php

namespace App\Filament\Resources\Promotions\Widgets;

use App\Models\Promotion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PromotionsStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalPromotions = Promotion::query()->count();
        $activePromotions = Promotion::query()->where('is_active', true)->count();
        $inactivePromotions = Promotion::query()->where('is_active', false)->count();
        $appPromotions = Promotion::query()->where('level', 'app')->count();
        $storePromotions = Promotion::query()->where('level', 'store')->count();

        return [
            Stat::make('عدد العروض', (string) $totalPromotions),
            Stat::make('العروض النشطة', (string) $activePromotions)
                ->color('success'),
            Stat::make('العروض غير النشطة', (string) $inactivePromotions)
                ->color('danger'),
            Stat::make('عدد عروض التطبيق', (string) $appPromotions)
                ->color('primary'),
            Stat::make('عدد عروض المتاجر', (string) $storePromotions)
                ->color('warning'),
        ];
    }
}
