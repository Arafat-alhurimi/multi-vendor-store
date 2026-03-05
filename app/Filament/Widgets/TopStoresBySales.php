<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\Store;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TopStoresBySales extends StatsOverviewWidget
{
    protected ?string $heading = 'أداء المتاجر';

    protected function getStats(): array
    {
        $topStore = Store::query()
            ->withCount('products')
            ->get()
            ->map(function (Store $store): array {
                $sales = Order::query()
                    ->whereHas('product', fn ($query) => $query->where('store_id', $store->id))
                    ->where('payment_status', Order::PAYMENT_VERIFIED)
                    ->sum('total_price');

                return [
                    'store' => $store,
                    'sales' => (float) $sales,
                ];
            })
            ->sortByDesc('sales')
            ->first();

        return [
            Stat::make('أكثر متجر مبيعًا', $topStore ? (string) $topStore['store']->name : '-')
                ->description($topStore ? number_format((float) $topStore['sales'], 2).' ر.س' : 'لا توجد مبيعات بعد')
                ->icon('heroicon-o-trophy')
                ->color('success'),
            Stat::make(
                'عدد منتجات المتجر الأعلى',
                $topStore ? (string) ((int) $topStore['store']->products_count) : '0'
            )
                ->icon('heroicon-o-cube')
                ->color('info'),
        ];
    }
}
