<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JoinedPromotionsRelationManager extends RelationManager
{
    protected static string $relationship = 'promotionItems';

    protected static ?string $title = 'عروض انضم لها المتجر';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('promotion.title')->label('العرض')->searchable(),
                Tables\Columns\TextColumn::make('target_type')
                    ->label('الانضمام على')
                    ->state(fn ($record): string => $this->mapPromotableType($record->promotable_type)),
                Tables\Columns\TextColumn::make('target_name')
                    ->label('الهدف المحدد')
                    ->state(fn ($record): string => $this->resolvePromotableName($record)),
                Tables\Columns\TextColumn::make('store_products_count')
                    ->label('عدد منتجات المتجر المنضمة')
                    ->state(fn ($record): int => $this->countJoinedProducts($record)),
                Tables\Columns\TextColumn::make('promotion.ends_at')
                    ->label('ينتهي في')
                    ->dateTime('Y-m-d H:i')
                    ->placeholder('-'),
                Tables\Columns\BadgeColumn::make('status')->label('حالة الانضمام'),
            ])
            ->filters([
                SelectFilter::make('promotion_status')
                    ->label('حالة العرض')
                    ->options([
                        'active' => 'نشط',
                        'ended' => 'منتهي',
                    ])
                    ->query(function ($query, array $data) {
                        $value = $data['value'] ?? null;

                        if ($value === 'active') {
                            return $query->whereHas('promotion', function ($promotionQuery) {
                                $promotionQuery->where('is_active', true)->where(function ($inner) {
                                    $inner->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                                });
                            });
                        }

                        if ($value === 'ended') {
                            return $query->whereHas('promotion', function ($promotionQuery) {
                                $promotionQuery->whereNotNull('ends_at')->where('ends_at', '<', now());
                            });
                        }

                        return $query;
                    }),
                SelectFilter::make('promotable_type')
                    ->label('نوع الانضمام')
                    ->options([
                        Product::class => 'منتج',
                        Category::class => 'قسم رئيسي',
                        Subcategory::class => 'قسم فرعي',
                        Store::class => 'المتجر كامل',
                    ]),
            ])
            ->headerActions([])
            ->actions([]);
    }

    private function mapPromotableType(?string $type): string
    {
        return match ($type) {
            Product::class => 'منتج',
            Category::class => 'قسم رئيسي',
            Subcategory::class => 'قسم فرعي',
            Store::class => 'المتجر كامل',
            default => '-',
        };
    }

    private function resolvePromotableName($record): string
    {
        $target = $record->promotable;

        if (! $target) {
            return '-';
        }

        if ($target instanceof Product) {
            return $target->name_ar ?: ('#' . $target->id);
        }

        if ($target instanceof Category) {
            return $target->name_ar ?: ('#' . $target->id);
        }

        if ($target instanceof Subcategory) {
            return $target->name_ar ?: ('#' . $target->id);
        }

        if ($target instanceof Store) {
            return $target->name ?: ('#' . $target->id);
        }

        return '#' . ($target->id ?? '');
    }

    private function countJoinedProducts($record): int
    {
        $store = $this->getOwnerRecord();

        if ($record->promotable_type === Store::class) {
            return (int) $store->products()->count();
        }

        if ($record->promotable_type === Product::class) {
            return (int) Product::query()->where('id', $record->promotable_id)->where('store_id', $store->id)->count();
        }

        if ($record->promotable_type === Category::class) {
            return (int) Product::query()
                ->where('store_id', $store->id)
                ->whereHas('subcategory', fn ($q) => $q->where('category_id', $record->promotable_id))
                ->count();
        }

        if ($record->promotable_type === Subcategory::class) {
            return (int) Product::query()
                ->where('store_id', $store->id)
                ->where('subcategory_id', $record->promotable_id)
                ->count();
        }

        return 0;
    }
}
