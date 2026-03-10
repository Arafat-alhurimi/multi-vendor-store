<?php

namespace App\Filament\Resources\AdPackageResource\Widgets;

use App\Models\AdPackage;
use App\Models\VendorAdSubscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdPackagesStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $packagesQuery = AdPackage::query();

        $totalPackages = (clone $packagesQuery)->count();
        $activePackages = (clone $packagesQuery)->where('is_active', true)->count();
        $inactivePackages = (clone $packagesQuery)->where('is_active', false)->count();

        $latestPackage = (clone $packagesQuery)
            ->latest('created_at')
            ->first(['name', 'created_at']);

        $latestPackageLabel = $latestPackage
            ? $latestPackage->name.' - '.$latestPackage->created_at?->format('Y-m-d')
            : '-';

        $allSubscribersCount = VendorAdSubscription::query()->count();

        return [
            Stat::make('إجمالي الباقات', (string) $totalPackages),
            Stat::make('الباقات النشطة', (string) $activePackages)->color('success'),
            Stat::make('الباقات غير النشطة', (string) $inactivePackages)->color('danger'),
            Stat::make('آخر باقة مضافة', $latestPackageLabel)->color('info'),
            Stat::make('عدد المشتركين بكل الباقات', (string) $allSubscribersCount)->color('warning'),
        ];
    }
}
