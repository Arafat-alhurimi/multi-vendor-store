<?php

namespace App\Filament\Resources\Users\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UsersStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $customersCount = User::query()->where('role', 'customer')->count();
        $activeCustomers = User::query()
            ->where('role', 'customer')
            ->where('is_active', true)
            ->count();

        $inactiveCustomers = User::query()
            ->where('role', 'customer')
            ->where('is_active', false)
            ->count();

        $customersJoinedLastWeek = User::query()
            ->where('role', 'customer')
            ->where('created_at', '>=', now()->subWeek())
            ->count();

        $customersWithCartItems = User::query()
            ->where('role', 'customer')
            ->whereHas('cartItems')
            ->count();

        $customersWithOrders = User::query()
            ->where('role', 'customer')
            ->whereHas('orders')
            ->count();

        return [
            Stat::make('عدد العملاء', (string) $customersCount)
                ->color('primary'),
            Stat::make('العملاء النشطون', (string) $activeCustomers)
                ->color('success'),
            Stat::make('العملاء غير النشطين', (string) $inactiveCustomers)
                ->color('danger'),
            Stat::make('عملاء انضموا آخر أسبوع', (string) $customersJoinedLastWeek)
                ->color('success'),
            Stat::make('عملاء لديهم سلة', (string) $customersWithCartItems)
                ->color('warning'),
            Stat::make('عملاء لديهم طلبات', (string) $customersWithOrders)
                ->color('info'),
        ];
    }
}
