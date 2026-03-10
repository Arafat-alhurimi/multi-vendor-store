<?php

namespace App\Filament\Resources\Reports\Widgets;

use App\Models\Comment;
use App\Models\Product;
use App\Models\Report;
use App\Models\Store;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportsStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $baseQuery = Report::query();

        $weekCount = (clone $baseQuery)
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        $storeCount = (clone $baseQuery)->where('reportable_type', Store::class)->count();
        $commentCount = (clone $baseQuery)->where('reportable_type', Comment::class)->count();
        $productCount = (clone $baseQuery)->where('reportable_type', Product::class)->count();

        $topStore = (clone $baseQuery)
            ->where('reportable_type', Store::class)
            ->selectRaw('reportable_id, COUNT(*) as reports_count')
            ->groupBy('reportable_id')
            ->orderByDesc('reports_count')
            ->first();

        $topProduct = (clone $baseQuery)
            ->where('reportable_type', Product::class)
            ->selectRaw('reportable_id, COUNT(*) as reports_count')
            ->groupBy('reportable_id')
            ->orderByDesc('reports_count')
            ->first();

        $topStoreModel = filled($topStore?->reportable_id)
            ? Store::query()->find((int) $topStore->reportable_id)
            : null;

        $topProductModel = filled($topProduct?->reportable_id)
            ? Product::query()->find((int) $topProduct->reportable_id)
            : null;

        $topStoreName = $topStoreModel
            ? (string) ($topStoreModel->name ?: ('#' . $topStoreModel->id))
            : '-';

        $topProductName = $topProductModel
            ? (string) ($topProductModel->name_ar ?: $topProductModel->name_en ?: ('#' . $topProductModel->id))
            : '-';

        return [
            Stat::make('بلاغات هذا الأسبوع', (string) $weekCount)
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),
            Stat::make('بلاغات المتاجر', (string) $storeCount)
                ->icon('heroicon-o-building-storefront')
                ->color('warning'),
            Stat::make('بلاغات التعليقات', (string) $commentCount)
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->color('danger'),
            Stat::make('بلاغات المنتجات', (string) $productCount)
                ->icon('heroicon-o-cube')
                ->color('info'),
            Stat::make('الأكثر بلاغًا (متجر)', $topStoreName)
                ->description($topStore ? ((int) $topStore->reports_count) . ' بلاغ' : 'لا توجد بلاغات')
                ->icon('heroicon-o-flag')
                ->color('warning'),
            Stat::make('الأكثر بلاغًا (منتج)', $topProductName)
                ->description($topProduct ? ((int) $topProduct->reports_count) . ' بلاغ' : 'لا توجد بلاغات')
                ->icon('heroicon-o-flag')
                ->color('danger'),
        ];
    }
}
