<?php

namespace App\Filament\Resources\Promotions\Widgets;

use App\Models\Promotion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PromotionsStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $appPromotionsQuery = Promotion::query()->where('level', 'app');

        $totalPromotions = (clone $appPromotionsQuery)->count();
        $activePromotions = (clone $appPromotionsQuery)->where('is_active', true)->count();
        $inactivePromotions = (clone $appPromotionsQuery)->where('is_active', false)->count();
        $withPendingJoinRequests = (clone $appPromotionsQuery)
            ->whereHas('items', fn ($query) => $query->where('status', 'pending'))
            ->count();

        return [
            Stat::make('إجمالي عروض التطبيق', (string) $totalPromotions),
            Stat::make('عروض التطبيق النشطة', (string) $activePromotions)
                ->color('success'),
            Stat::make('عروض التطبيق غير النشطة', (string) $inactivePromotions)
                ->color('danger'),
            Stat::make('عروض لديها طلبات انضمام', (string) $withPendingJoinRequests)
                ->color('warning'),
        ];
    }
}
