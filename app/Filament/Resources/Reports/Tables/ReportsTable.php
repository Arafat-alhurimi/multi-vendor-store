<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Report;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('المبلغ')
                    ->searchable(),
                TextColumn::make('reportable_type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        Product::class => 'info',
                        Store::class => 'warning',
                        Comment::class => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Product::class => 'منتج',
                        Store::class => 'متجر',
                        Comment::class => 'تعليق',
                        default => 'غير معروف',
                    }),
                TextColumn::make('target_name')
                    ->label('العنصر المبلغ عنه')
                    ->state(fn (Report $record): string => static::resolveTargetName($record))
                    ->url(fn (Report $record): ?string => static::resolveTargetUrl($record))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->limit(45),
                TextColumn::make('reason')
                    ->label('سبب البلاغ')
                    ->limit(80)
                    ->tooltip(fn (Report $record): ?string => filled($record->reason) ? (string) $record->reason : null),
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->since(),
            ])
            ->filters([
                SelectFilter::make('reportable_type')
                    ->label('نوع البلاغ')
                    ->options([
                        Product::class => 'منتج',
                        Store::class => 'متجر',
                        Comment::class => 'تعليق',
                    ]),
                TernaryFilter::make('this_week')
                    ->label('بلاغات هذا الأسبوع')
                    ->trueLabel('هذا الأسبوع')
                    ->falseLabel('قبل هذا الأسبوع')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('created_at', '>=', now()->startOfWeek()),
                        false: fn (Builder $query): Builder => $query->where('created_at', '<', now()->startOfWeek()),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('created_between')
                    ->label('تاريخ البلاغ')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(filled($data['from'] ?? null), fn (Builder $q): Builder => $q->whereDate('created_at', '>=', $data['from']))
                            ->when(filled($data['until'] ?? null), fn (Builder $q): Builder => $q->whereDate('created_at', '<=', $data['until']));
                    }),
                Filter::make('reported_store')
                    ->label('المتجر المُبلّغ عنه')
                    ->form([
                        Select::make('store_id')
                            ->label('المتجر')
                            ->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->toArray())
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $storeId = $data['store_id'] ?? null;

                        if (! filled($storeId)) {
                            return $query;
                        }

                        return $query
                            ->where('reportable_type', Store::class)
                            ->where('reportable_id', (int) $storeId);
                    }),
                Filter::make('reported_product')
                    ->label('المنتج المُبلّغ عنه')
                    ->form([
                        Select::make('product_id')
                            ->label('المنتج')
                            ->options(fn (): array => Product::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray())
                            ->searchable(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $productId = $data['product_id'] ?? null;

                        if (! filled($productId)) {
                            return $query;
                        }

                        return $query
                            ->where('reportable_type', Product::class)
                            ->where('reportable_id', (int) $productId);
                    }),
            ])
            ->recordUrl(null)
            ->recordAction('viewDetails')
            ->recordActions([
                Action::make('viewDetails')
                    ->label('عرض البلاغ')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('تفاصيل البلاغ')
                    ->slideOver()
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Report $record) => view('filament.reports.report-details-modal', [
                        'report' => $record,
                        'targetLabel' => static::resolveTargetName($record),
                        'targetTypeLabel' => static::resolveTargetTypeLabel($record),
                        'targetUrl' => static::resolveTargetUrl($record),
                    ])),
                Action::make('openTarget')
                    ->label('فتح العنصر')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Report $record): ?string => static::resolveTargetUrl($record))
                    ->openUrlInNewTab(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function resolveTargetTypeLabel(Report $record): string
    {
        return match ($record->reportable_type) {
            Product::class => 'منتج',
            Store::class => 'متجر',
            Comment::class => 'تعليق',
            default => 'غير معروف',
        };
    }

    private static function resolveTargetName(Report $record): string
    {
        $reportable = $record->reportable;

        if ($reportable instanceof Product) {
            return (string) ($reportable->name_ar ?: $reportable->name_en ?: ('#' . $reportable->id));
        }

        if ($reportable instanceof Store) {
            return (string) ($reportable->name ?: ('#' . $reportable->id));
        }

        if ($reportable instanceof Comment) {
            return 'تعليق #' . $reportable->id;
        }

        return '#'.(string) $record->reportable_id;
    }

    private static function resolveTargetUrl(Report $record): ?string
    {
        $reportable = $record->reportable;

        if ($reportable instanceof Product) {
            return ProductResource::getUrl('view', ['record' => $reportable]);
        }

        if ($reportable instanceof Store) {
            return StoreResource::getUrl('view', ['record' => $reportable]);
        }

        if ($reportable instanceof Comment) {
            $commentUserId = (int) ($reportable->user_id ?? 0);

            if ($commentUserId > 0) {
                return UserResource::getUrl('view', ['record' => $commentUserId]) . '#comment-' . $reportable->id;
            }
        }

        return null;
    }
}
