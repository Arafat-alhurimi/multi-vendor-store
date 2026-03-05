<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommerceStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalRevenue = Order::query()
            ->where('payment_status', Order::PAYMENT_VERIFIED)
            ->sum('total_price');

        $processingOrders = Order::query()
            ->where('status', Order::STATUS_PROCESSING)
            ->count();

        $pendingVendors = User::query()
            ->where('role', 'vendor')
            ->where('is_active', false)
            ->count();

        $totalCustomers = User::query()
            ->where('role', 'customer')
            ->count();

        return [
            Stat::make('إجمالي المبيعات', number_format((float) $totalRevenue, 2).' ر.س')
                ->description('إجمالي المدفوعات المتحقق منها')
                ->color('success')
                ->icon('heroicon-o-banknotes'),
            Stat::make('الطلبات قيد المعالجة', (string) $processingOrders)
                ->description('طلبات بحالة تجهيز')
                ->color('warning')
                ->icon('heroicon-o-arrow-path'),
            Stat::make('تجار بانتظار التفعيل', (string) $pendingVendors)
                ->description('حسابات بائعين غير مفعلة')
                ->color('info')
                ->icon('heroicon-o-user-minus'),
            Stat::make('إجمالي العملاء', (string) $totalCustomers)
                ->description('عدد حسابات العملاء')
                ->color('primary')
                ->icon('heroicon-o-users'),
        ];
    }
}
