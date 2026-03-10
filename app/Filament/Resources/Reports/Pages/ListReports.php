<?php

namespace App\Filament\Resources\Reports\Pages;

use App\Filament\Resources\Reports\ReportResource;
use App\Filament\Resources\Reports\Widgets\ReportsStatsOverview;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Report;
use App\Models\Store;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListReports extends ListRecords
{
    protected static string $resource = ReportResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            ReportsStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge((string) Report::query()->count()),
            'store' => Tab::make('متجر')
                ->badge((string) Report::query()->where('reportable_type', Store::class)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('reportable_type', Store::class)),
            'comment' => Tab::make('تعليق')
                ->badge((string) Report::query()->where('reportable_type', Comment::class)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('reportable_type', Comment::class)),
            'product' => Tab::make('منتج')
                ->badge((string) Report::query()->where('reportable_type', Product::class)->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('reportable_type', Product::class)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
