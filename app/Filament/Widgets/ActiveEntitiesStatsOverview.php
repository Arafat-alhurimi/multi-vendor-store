<?php

namespace App\Filament\Widgets;

use App\Models\AdPackage;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Report;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorAdSubscription;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveEntitiesStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'إحصائيات الكيانات النشطة';

    protected function getStats(): array
    {
        $activeStores = Store::query()->where('is_active', true)->count();
        $activeCustomers = User::query()->where('role', 'customer')->where('is_active', true)->count();
        $activeMainCategories = Category::query()->where('is_active', true)->count();

        $activeAppPromotions = Promotion::query()
            ->appLevel()
            ->currentlyActive(now())
            ->count();

        $activeStorePromotions = Promotion::query()
            ->storeLevel()
            ->currentlyActive(now())
            ->count();

        $activeProducts = Product::query()->where('is_active', true)->count();
        $activePackageSubscriptions = VendorAdSubscription::query()->active(now())->count();
        $activePackages = AdPackage::query()->where('is_active', true)->count();

        // Comments and reports currently have no active/inactive status field.
        $totalComments = Comment::query()->count();
        $totalReports = Report::query()->count();

        return [
            Stat::make('المتاجر النشطة', (string) $activeStores)->color('success')->icon('heroicon-o-building-storefront'),
            Stat::make('العملاء النشطون', (string) $activeCustomers)->color('primary')->icon('heroicon-o-users'),
            Stat::make('الفئات الرئيسية النشطة', (string) $activeMainCategories)->color('info')->icon('heroicon-o-squares-2x2'),
            Stat::make('عروض التطبيق النشطة', (string) $activeAppPromotions)->color('warning')->icon('heroicon-o-megaphone'),
            Stat::make('عروض المتاجر النشطة', (string) $activeStorePromotions)->color('warning')->icon('heroicon-o-tag'),
            Stat::make('المنتجات النشطة', (string) $activeProducts)->color('success')->icon('heroicon-o-cube'),
            Stat::make('اشتراكات الباقات النشطة', (string) $activePackageSubscriptions)->color('primary')->icon('heroicon-o-credit-card'),
            Stat::make('الباقات النشطة', (string) $activePackages)->color('info')->icon('heroicon-o-archive-box'),
            Stat::make('إجمالي التعليقات', (string) $totalComments)
                ->color('gray')
                ->icon('heroicon-o-chat-bubble-left-right'),
            Stat::make('إجمالي البلاغات', (string) $totalReports)
                ->color('danger')
                ->icon('heroicon-o-flag'),
        ];
    }
}
