<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\CommerceStatsOverview;
use App\Filament\Widgets\LowStockAlerts;
use App\Filament\Widgets\OrderStatusPieChart;
use App\Filament\Widgets\SalesLast7DaysChart;
use App\Filament\Widgets\TopStoresBySales;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'لوحة التحكم الرئيسية';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createStore')
                ->label('إضافة متجر جديد')
                ->icon('heroicon-o-building-storefront')
                ->color('success')
                ->url(StoreResource::getUrl('create')),
            Action::make('createUser')
                ->label('إضافة مستخدم')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->url(UserResource::getUrl('create')),
            Action::make('latestOrders')
                ->label('مراجعة آخر الطلبات')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->url(OrderResource::getUrl('index', [
                    'tableSortColumn' => 'created_at',
                    'tableSortDirection' => 'desc',
                ])),
            Action::make('allProducts')
                ->label('كل المنتجات')
                ->icon('heroicon-o-cube')
                ->color('gray')
                ->url(ProductResource::getUrl('index')),
        ];
    }

    public function getWidgets(): array
    {
        return [
            CommerceStatsOverview::class,
            SalesLast7DaysChart::class,
            OrderStatusPieChart::class,
            TopStoresBySales::class,
            LowStockAlerts::class,
        ];
    }

    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }
}
