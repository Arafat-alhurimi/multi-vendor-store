<?php

namespace App\Filament\Resources\Subcategories\Widgets;

use App\Models\Subcategory;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SubcategoriesStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalSubcategories = Subcategory::query()->count();
        $activeSubcategories = Subcategory::query()->where('is_active', true)->count();
        $inactiveSubcategories = Subcategory::query()->where('is_active', false)->count();

        return [
            Stat::make('إجمالي الفئات الفرعية', (string) $totalSubcategories),
            Stat::make('إجمالي الفئات الفرعية النشطة', (string) $activeSubcategories)
                ->color('success'),
            Stat::make('إجمالي الفئات الفرعية غير النشطة', (string) $inactiveSubcategories)
                ->color('danger'),
        ];
    }
}
