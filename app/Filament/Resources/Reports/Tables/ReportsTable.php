<?php

namespace App\Filament\Resources\Reports\Tables;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Report;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->label('نوع البلاغ')
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        Product::class => 'منتج',
                        Store::class => 'متجر',
                        Comment::class => 'تعليق',
                        default => 'غير معروف',
                    }),
                TextColumn::make('reason')
                    ->label('سبب البلاغ')
                    ->limit(60),
                TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->since(),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                Action::make('openTarget')
                    ->label('فتح العنصر')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (Report $record): ?string {
                        $reportable = $record->reportable;

                        if ($reportable instanceof Product) {
                            return ProductResource::getUrl('view', ['record' => $reportable]);
                        }

                        if ($reportable instanceof Store) {
                            return StoreResource::getUrl('view', ['record' => $reportable]);
                        }

                        if ($reportable instanceof Comment) {
                            $commentable = $reportable->commentable;

                            if ($commentable instanceof Product) {
                                return ProductResource::getUrl('view', ['record' => $commentable]);
                            }

                            if ($commentable instanceof Store) {
                                return StoreResource::getUrl('view', ['record' => $commentable]);
                            }
                        }

                        return null;
                    })
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
