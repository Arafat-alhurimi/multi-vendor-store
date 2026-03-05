<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OwnPromotionsRelationManager extends RelationManager
{
    protected static string $relationship = 'ownPromotions';

    protected static ?string $title = 'عروض أضافها المتجر';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn ($record): string => PromotionResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('العرض')->searchable(),
                Tables\Columns\TextColumn::make('target_details')
                    ->label('العرض على')
                    ->state(fn ($record): string => $this->resolvePromotionTargetDetails($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('affected_products_count')
                    ->label('عدد المنتجات الخاضعة')
                    ->state(fn ($record): int => $this->countAffectedProducts($record)),
                Tables\Columns\TextColumn::make('discount_type')->label('نوع الخصم'),
                Tables\Columns\TextColumn::make('discount_value')->label('قيمة الخصم'),
                Tables\Columns\TextColumn::make('ends_at')->label('ينتهي في')->dateTime('Y-m-d H:i')->placeholder('-'),
                Tables\Columns\IconColumn::make('effective_active')
                    ->label('نشط؟')
                    ->boolean()
                    ->state(fn ($record): bool => $record->isEffectivelyActive()),
            ])
            ->filters([
                SelectFilter::make('activity')
                    ->label('حالة العرض')
                    ->options([
                        'active' => 'نشط',
                        'ended' => 'منتهي',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if ($value === 'active') {
                            return $query->where('is_active', true)->where(function ($q) {
                                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                            });
                        }

                        if ($value === 'ended') {
                            return $query->whereNotNull('ends_at')->where('ends_at', '<', now());
                        }

                        return $query;
                    }),
            ])
            ->headerActions([])
            ->actions([]);
    }

    private function resolvePromotionTargetDetails($promotion): string
    {
        $items = $promotion->items()->with('promotable')->get();

        if ($items->isEmpty()) {
            return 'كل منتجات المتجر';
        }

        $labels = $items->map(function ($item): string {
            $target = $item->promotable;

            if (! $target) {
                return 'غير محدد';
            }

            return match ($item->promotable_type) {
                Product::class => 'منتج: ' . ($target->name_ar ?: ('#' . $target->id)),
                Category::class => 'قسم رئيسي: ' . ($target->name_ar ?: ('#' . $target->id)),
                Subcategory::class => 'قسم فرعي: ' . ($target->name_ar ?: ('#' . $target->id)),
                Store::class => 'المتجر كامل',
                default => 'هدف: #' . $target->id,
            };
        })->unique()->values();

        if ($labels->count() <= 3) {
            return $labels->implode(' | ');
        }

        return $labels->take(3)->implode(' | ') . ' ... +' . ($labels->count() - 3);
    }

    private function countAffectedProducts($promotion): int
    {
        $store = $this->getOwnerRecord();
        $items = $promotion->items()->with('promotable')->get();

        if ($items->isEmpty()) {
            return (int) $store->products()->count();
        }

        $productIds = collect();

        foreach ($items as $item) {
            $targetId = $item->promotable_id;

            if (! $targetId) {
                continue;
            }

            if ($item->promotable_type === Product::class) {
                $ids = Product::query()
                    ->where('store_id', $store->id)
                    ->whereKey($targetId)
                    ->pluck('id');

                $productIds = $productIds->merge($ids);
                continue;
            }

            if ($item->promotable_type === Category::class) {
                $ids = Product::query()
                    ->where('store_id', $store->id)
                    ->whereHas('subcategory', fn ($query) => $query->where('category_id', $targetId))
                    ->pluck('id');

                $productIds = $productIds->merge($ids);
                continue;
            }

            if ($item->promotable_type === Subcategory::class) {
                $ids = Product::query()
                    ->where('store_id', $store->id)
                    ->where('subcategory_id', $targetId)
                    ->pluck('id');

                $productIds = $productIds->merge($ids);
                continue;
            }

            if ($item->promotable_type === Store::class && (int) $targetId === (int) $store->id) {
                $ids = Product::query()
                    ->where('store_id', $store->id)
                    ->pluck('id');

                $productIds = $productIds->merge($ids);
            }
        }

        return $productIds->unique()->count();
    }
}
