<?php

namespace App\Filament\Resources\CategoryResource\Widgets;

use App\Models\Category;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CategoriesStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalCategories = Category::query()->count();
        $activeCategories = Category::query()->where('is_active', true)->count();
        $inactiveCategories = Category::query()->where('is_active', false)->count();

        return [
            Stat::make('إجمالي الفئات', (string) $totalCategories),
            Stat::make('إجمالي الفئات النشطة', (string) $activeCategories)
                ->color('success'),
            Stat::make('إجمالي الفئات غير النشطة', (string) $inactiveCategories)
                ->color('danger'),
        ];
    }
}
