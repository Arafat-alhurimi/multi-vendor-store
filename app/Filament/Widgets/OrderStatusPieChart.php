<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrderStatusPieChart extends ChartWidget
{
    protected ?string $heading = 'توزّع حالات الطلبات';

    protected int | string | array $columnSpan = 2;

    protected function getType(): string
    {
        return 'pie';
    }

    protected function getData(): array
    {
        $delivered = Order::query()->where('status', Order::STATUS_DELIVERED)->count();
        $pending = Order::query()->where('status', Order::STATUS_PENDING)->count();
        $cancelled = Order::query()->where('status', Order::STATUS_CANCELLED)->count();

        return [
            'datasets' => [
                [
                    'label' => 'حالات الطلبات',
                    'data' => [$delivered, $pending, $cancelled],
                    'backgroundColor' => ['#10b981', '#f59e0b', '#ef4444'],
                ],
            ],
            'labels' => ['تم التسليم', 'معلق', 'ملغي'],
        ];
    }
}
