<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name_ar')
                    ->label('الاسم (عربي)')
                    ->searchable(),
                TextColumn::make('store.name')
                    ->label('المتجر')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section_path')
                    ->label('القسم')
                    ->state(fn (Product $record): string => collect([
                        $record->subcategory?->category?->name_ar,
                        $record->subcategory?->name_ar,
                    ])->filter()->implode('>') ?: '-')
                    ->badge()
                    ->color('info')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner
                                ->whereHas('subcategory', fn (Builder $sub): Builder => $sub->where('name_ar', 'like', "%{$search}%"))
                                ->orWhereHas('subcategory.category', fn (Builder $cat): Builder => $cat->where('name_ar', 'like', "%{$search}%"));
                        });
                    })
                    ->placeholder('-'),
                TextColumn::make('base_price')
                    ->label('السعر')
                    ->money('SAR')
                    ->sortable(),
                TextColumn::make('final_price')
                    ->label('السعر بعد العرض')
                    ->state(fn (Product $record): string => $record->final_price)
                    ->money('SAR'),
                TextColumn::make('stock')
                    ->label('المخزون')
                    ->sortable(),
                IconColumn::make('is_under_offer')
                    ->label('ضمن عرض؟')
                    ->boolean()
                    ->state(fn (Product $record): bool => (float) $record->final_price < (float) $record->base_price),
                TextColumn::make('cart_total_count')
                    ->label('إضافات السلة')
                    ->state(fn (Product $record): int =>
                        (int) $record->cartItems()->count()
                        + (int) $record->variants()->withCount('cartItems')->get()->sum('cart_items_count')
                    ),
                TextColumn::make('orders_total_count')
                    ->label('الطلبات')
                    ->state(fn (Product $record): int =>
                        (int) $record->orders()->count()
                        + (int) $record->variants()->withCount('orders')->get()->sum('orders_count')
                    ),
                TextColumn::make('avg_rating')
                    ->label('متوسط التقييم')
                    ->state(function (Product $record): ?string {
                        $avgRating = $record->ratings()->avg('value');

                        return $avgRating === null ? null : number_format((float) $avgRating, 2);
                    })
                    ->placeholder(''),
                TextColumn::make('favorites_count')
                    ->label('عدد المفضلة')
                    ->state(fn (Product $record): int => (int) $record->favorites()->count()),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),
            ])
            ->filters([
                TernaryFilter::make('is_under_offer')
                    ->label('هل يخضع لعرض؟')
                    ->trueLabel('ضمن عرض')
                    ->falseLabel('بدون عرض')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('promotionItems'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('promotionItems'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('in_cart')
                    ->label('مضاف للسلة')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner->whereHas('cartItems')->orWhereHas('variants.cartItems');
                        }),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('cartItems')->whereDoesntHave('variants.cartItems'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_orders')
                    ->label('له طلبات')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner->whereHas('orders')->orWhereHas('variants.orders');
                        }),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('orders')->whereDoesntHave('variants.orders'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_favorites')
                    ->label('له إضافات مفضلة')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('favorites'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('favorites'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_ratings')
                    ->label('له تقييمات')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('ratings'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('ratings'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط')
                    ->placeholder('الكل'),
                SelectFilter::make('store_id')
                    ->label('المتجر')
                    ->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),
                SelectFilter::make('category')
                    ->label('الفئة الرئيسية')
                    ->options(fn (): array => Category::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (! $value) {
                            return $query;
                        }

                        return $query->whereHas('subcategory', fn (Builder $subQuery): Builder => $subQuery->where('category_id', $value));
                    }),
                SelectFilter::make('subcategory_id')
                    ->label('القسم الفرعي')
                    ->options(fn (): array => Subcategory::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray())
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('viewProduct')
                    ->label('عرض المنتج')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record): string => \App\Filament\Resources\Products\ProductResource::getUrl('view', ['record' => $record])),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
