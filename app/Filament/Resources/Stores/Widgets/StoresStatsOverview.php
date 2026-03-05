<?php

namespace App\Filament\Resources\Stores\Widgets;

use App\Models\Store;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StoresStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalStores = Store::query()->count();
        $activeStores = Store::query()->where('is_active', true)->count();
        $inactiveStores = Store::query()->where('is_active', false)->count();
        $storesLastWeek = Store::query()->where('created_at', '>=', now()->subWeek())->count();
        $storesLastMonth = Store::query()->where('created_at', '>=', now()->subMonth())->count();
        $latestStore = Store::query()->latest('created_at')->first();

        return [
            Stat::make('إجمالي المتاجر', (string) $totalStores),
            Stat::make('المتاجر النشطة', (string) $activeStores)
                ->color('success'),
            Stat::make('المتاجر غير النشطة', (string) $inactiveStores)
                ->color('danger'),
            Stat::make('متاجر أُضيفت آخر أسبوع', (string) $storesLastWeek),
            Stat::make('متاجر أُضيفت آخر شهر', (string) $storesLastMonth),
            Stat::make('آخر متجر أُضيف', $latestStore?->name ?? '-')
                ->description($latestStore?->created_at?->diffForHumans() ?? ''),
        ];
    }
}
