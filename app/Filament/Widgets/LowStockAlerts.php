<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LowStockAlerts extends StatsOverviewWidget
{
    protected ?string $heading = 'تنبيهات المخزون';

    protected function getStats(): array
    {
        $outOfStockProducts = Product::query()->where('stock', '<=', 0)->count();
        $lowStockProducts = Product::query()->where('stock', '>', 0)->where('stock', '<=', 5)->count();

        $outOfStockVariants = ProductVariant::query()->where('stock', '<=', 0)->count();
        $lowStockVariants = ProductVariant::query()->where('stock', '>', 0)->where('stock', '<=', 3)->count();

        return [
            Stat::make('منتجات نافدة المخزون', (string) $outOfStockProducts)
                ->icon('heroicon-o-no-symbol')
                ->color('danger'),
            Stat::make('منتجات منخفضة المخزون', (string) $lowStockProducts)
                ->icon('heroicon-o-exclamation-triangle')
                ->color('warning'),
            Stat::make('منتجات فرعية نافدة', (string) $outOfStockVariants)
                ->icon('heroicon-o-minus-circle')
                ->color('danger'),
            Stat::make('منتجات فرعية منخفضة', (string) $lowStockVariants)
                ->icon('heroicon-o-bell-alert')
                ->color('warning'),
        ];
    }
}
