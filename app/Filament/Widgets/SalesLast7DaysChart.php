<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class SalesLast7DaysChart extends ChartWidget
{
    protected ?string $heading = 'مبيعات آخر 7 أيام';

    protected int | string | array $columnSpan = 2;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $labels = [];
        $data = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->translatedFormat('D d/m');

            $dailyTotal = Order::query()
                ->where('payment_status', Order::PAYMENT_VERIFIED)
                ->whereDate('created_at', $date->toDateString())
                ->sum('total_price');

            $data[] = round((float) $dailyTotal, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'المبيعات',
                    'data' => $data,
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
