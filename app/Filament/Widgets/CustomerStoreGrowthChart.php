<?php

namespace App\Filament\Widgets;

use App\Models\Store;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class CustomerStoreGrowthChart extends ChartWidget
{
    protected ?string $heading = 'ازدياد العملاء والمتاجر (آخر 6 أشهر)';

    protected int | string | array $columnSpan = 2;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $labels = [];
        $customersData = [];
        $storesData = [];

        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            $labels[] = $monthStart->translatedFormat('M Y');

            $customersData[] = User::query()
                ->where('role', 'customer')
                ->where('is_active', true)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();

            $storesData[] = Store::query()
                ->where('is_active', true)
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'عملاء جدد نشطون',
                    'data' => $customersData,
                    'borderColor' => '#2563eb',
                    'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
                [
                    'label' => 'متاجر جديدة نشطة',
                    'data' => $storesData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
