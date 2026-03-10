<?php

namespace App\Filament\Resources\StorePromotions\Widgets;

use App\Models\Promotion;
use App\Models\Store;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StorePromotionsStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $storePromotionsQuery = Promotion::query()
            ->where('level', 'store');

        $totalPromotions = (clone $storePromotionsQuery)->count();
        $activePromotions = (clone $storePromotionsQuery)->where('is_active', true)->count();
        $inactivePromotions = (clone $storePromotionsQuery)->where('is_active', false)->count();
        $addedThisWeek = (clone $storePromotionsQuery)
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();

        $topStoreStats = (clone $storePromotionsQuery)
            ->whereNotNull('store_id')
            ->selectRaw('store_id, COUNT(*) as offers_count')
            ->groupBy('store_id')
            ->orderByDesc('offers_count')
            ->first();

        $topStoreName = $topStoreStats?->store_id
            ? Store::query()->whereKey($topStoreStats->store_id)->value('name')
            : null;

        $topStoreValue = $topStoreName
            ? $topStoreName.' ('.(int) $topStoreStats->offers_count.')'
            : '-';

        return [
            Stat::make('إجمالي عروض المتاجر', (string) $totalPromotions),
            Stat::make('عروض المتاجر النشطة', (string) $activePromotions)
                ->color('success'),
            Stat::make('عروض المتاجر غير النشطة', (string) $inactivePromotions)
                ->color('danger'),
            Stat::make('العروض المضافة هذا الأسبوع', (string) $addedThisWeek)
                ->color('info'),
            Stat::make('أكثر متجر لديه عروض', $topStoreValue)
                ->color('warning'),
        ];
    }
}
