<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('level', 'store'))
            ->recordAction('viewDetails')
            ->recordUrl(null)
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('صورة العرض')
                    ->disk('s3')
                    ->circular()
                    ->defaultImageUrl(null),
                Tables\Columns\TextColumn::make('title')
                    ->label('اسم العرض')
                    ->searchable(),
                Tables\Columns\TextColumn::make('target_details')
                    ->label('على ماذا؟')
                    ->state(fn (Promotion $record): string => $this->resolvePromotionTargetDetails($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('products_in_offer_count')
                    ->label('عدد المنتجات')
                    ->state(fn (Promotion $record): int => $this->resolvePromotionTargetProductIds($record)->count()),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('يبدأ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('ينتهي')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('discount_type')
                    ->label('نوع الخصم')
                    ->formatStateUsing(fn (string $state): string => $state === 'percentage' ? 'نسبة مئوية' : 'قيمة ثابتة')
                    ->colors([
                        'info' => 'percentage',
                        'primary' => 'fixed',
                    ]),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('قيمة الخصم')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->headerActions([])
            ->actions([
                Action::make('viewDetails')
                    ->label('تفاصيل العرض')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('تفاصيل عرض المتجر')
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Promotion $record) => view('filament.store-promotions.promotion-details-modal', [
                        'promotion' => $record,
                        'summary' => $this->resolvePromotionDetailsSummary($record),
                        'products' => $this->resolvePromotionTargetProductsWithMetrics($record),
                    ])),
            ]);
    }

    private function resolvePromotionTargetDetails(Promotion $promotion): string
    {
        $items = PromotionItem::query()
            ->where('promotion_id', $promotion->id)
            ->approved()
            ->with('promotable')
            ->get(['promotable_type', 'promotable_id', 'store_id']);

        if ($items->isEmpty()) {
            return 'غير محدد';
        }

        $labels = $items->map(function (PromotionItem $item): string {
            $target = $item->promotable;
            return match ($item->promotable_type) {
                Store::class => 'متجر كامل: ' . ($target?->name ?? ('#' . $item->promotable_id)),
                Category::class => 'قسم رئيسي: ' . ($target?->name_ar ?? ('#' . $item->promotable_id)),
                Subcategory::class => 'قسم فرعي: ' . ($target?->name_ar ?? ('#' . $item->promotable_id)),
                Product::class => 'منتج: ' . ($target?->name_ar ?? ('#' . $item->promotable_id)),
                default => class_basename((string) $item->promotable_type) . ': #' . $item->promotable_id,
            };
        })->unique()->values();

        if ($labels->count() <= 2) {
            return $labels->implode(' | ');
        }

        return $labels->take(2)->implode(' | ') . ' ... +' . ($labels->count() - 2);
    }

    private function resolvePromotionTargetProductIds(Promotion $promotion): Collection
    {
        $ownerStoreId = (int) $this->getOwnerRecord()->id;

        $items = PromotionItem::query()
            ->where('promotion_id', $promotion->id)
            ->approved()
            ->get(['promotable_type', 'promotable_id', 'store_id']);

        if ($items->isEmpty()) {
            return collect();
        }

        $productIds = collect();

        foreach ($items as $item) {
            $effectiveStoreId = $item->store_id ?: $ownerStoreId;

            if ($item->promotable_type === Product::class) {
                $productIds = $productIds->merge(
                    Product::query()
                        ->whereKey($item->promotable_id)
                        ->where('store_id', $effectiveStoreId)
                        ->pluck('id')
                );
                continue;
            }

            if ($item->promotable_type === Store::class) {
                $productIds = $productIds->merge(
                    Product::query()
                        ->where('store_id', $item->promotable_id)
                        ->where('store_id', $ownerStoreId)
                        ->pluck('id')
                );
                continue;
            }

            if ($item->promotable_type === Subcategory::class) {
                $productIds = $productIds->merge(
                    Product::query()
                        ->where('subcategory_id', $item->promotable_id)
                        ->where('store_id', $effectiveStoreId)
                        ->pluck('id')
                );
                continue;
            }

            if ($item->promotable_type === Category::class) {
                $productIds = $productIds->merge(
                    Product::query()
                        ->whereHas('subcategory', fn (Builder $query): Builder => $query->where('category_id', $item->promotable_id))
                        ->where('store_id', $effectiveStoreId)
                        ->pluck('id')
                );
            }
        }

        return $productIds->filter()->unique()->values();
    }

    private function resolvePromotionTargetProductsWithMetrics(Promotion $promotion): Collection
    {
        $productIds = $this->resolvePromotionTargetProductIds($promotion);

        if ($productIds->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->with(['variants:id,product_id,price', 'subcategory:id,name_ar,category_id', 'subcategory.category:id,name_ar'])
            ->whereIn('id', $productIds->all())
            ->get(['id', 'subcategory_id', 'name_ar', 'base_price', 'stock'])
            ->map(function (Product $product) use ($promotion): array {
                $basePrice = (float) $product->base_price;
                $afterPrice = $this->applyPromotionDiscount($basePrice, (string) $promotion->discount_type, (float) $promotion->discount_value);

                return [
                    'id' => $product->id,
                    'name_ar' => $product->name_ar,
                    'category_name' => $product->subcategory?->category?->name_ar,
                    'subcategory_name' => $product->subcategory?->name_ar,
                    'base_price' => number_format($basePrice, 2, '.', ''),
                    'after_price' => number_format($afterPrice, 2, '.', ''),
                    'stock' => (int) $product->stock,
                    'cart_additions_count' => $this->countProductBenefitedCartAdditions($product, $promotion),
                    'orders_count' => $this->countProductBenefitedOrders($product, $promotion),
                ];
            })
            ->values();
    }

    private function resolvePromotionDetailsSummary(Promotion $promotion): array
    {
        $productIds = $this->resolvePromotionTargetProductIds($promotion);

        $products = Product::query()
            ->whereIn('id', $productIds->all())
            ->with('variants:id,product_id,price')
            ->get(['id', 'base_price']);

        return [
            'products_count' => $products->count(),
            'cart_additions_count' => (int) $products->sum(fn (Product $product): int => $this->countProductBenefitedCartAdditions($product, $promotion)),
            'orders_count' => (int) $products->sum(fn (Product $product): int => $this->countProductBenefitedOrders($product, $promotion)),
        ];
    }

    private function countProductBenefitedCartAdditions(Product $product, Promotion $promotion): int
    {
        $discountType = (string) $promotion->discount_type;
        $discountValue = (float) $promotion->discount_value;

        $baseOfferPrice = number_format(
            $this->applyPromotionDiscount((float) $product->base_price, $discountType, $discountValue),
            2,
            '.',
            ''
        );

        $variantOfferPrices = $product->variants
            ->mapWithKeys(fn ($variant) => [
                (int) $variant->id => number_format(
                    $this->applyPromotionDiscount((float) ($variant->price ?? $product->base_price), $discountType, $discountValue),
                    2,
                    '.',
                    ''
                ),
            ]);

        return (int) CartItem::query()
            ->where('product_id', $product->id)
            ->where(function (Builder $query) use ($baseOfferPrice, $variantOfferPrices): void {
                $query->where(function (Builder $baseQuery) use ($baseOfferPrice): void {
                    $baseQuery
                        ->whereNull('product_variation_id')
                        ->where('price_at_add', $baseOfferPrice);
                });

                foreach ($variantOfferPrices as $variantId => $offerPrice) {
                    $query->orWhere(function (Builder $variantQuery) use ($variantId, $offerPrice): void {
                        $variantQuery
                            ->where('product_variation_id', $variantId)
                            ->where('price_at_add', $offerPrice);
                    });
                }
            })
            ->when($promotion->starts_at, fn (Builder $query): Builder => $query->where('created_at', '>=', $promotion->starts_at))
            ->when($promotion->ends_at, fn (Builder $query): Builder => $query->where('created_at', '<=', $promotion->ends_at))
            ->count();
    }

    private function countProductBenefitedOrders(Product $product, Promotion $promotion): int
    {
        $discountType = (string) $promotion->discount_type;
        $discountValue = (float) $promotion->discount_value;

        $baseOfferPrice = number_format(
            $this->applyPromotionDiscount((float) $product->base_price, $discountType, $discountValue),
            2,
            '.',
            ''
        );

        $variantOfferPrices = $product->variants
            ->mapWithKeys(fn ($variant) => [
                (int) $variant->id => number_format(
                    $this->applyPromotionDiscount((float) ($variant->price ?? $product->base_price), $discountType, $discountValue),
                    2,
                    '.',
                    ''
                ),
            ]);

        return (int) Order::query()
            ->where('product_id', $product->id)
            ->where(function (Builder $query) use ($baseOfferPrice, $variantOfferPrices): void {
                $query->where(function (Builder $baseQuery) use ($baseOfferPrice): void {
                    $baseQuery
                        ->whereNull('product_variation_id')
                        ->where('unit_price', $baseOfferPrice);
                });

                foreach ($variantOfferPrices as $variantId => $offerPrice) {
                    $query->orWhere(function (Builder $variantQuery) use ($variantId, $offerPrice): void {
                        $variantQuery
                            ->where('product_variation_id', $variantId)
                            ->where('unit_price', $offerPrice);
                    });
                }
            })
            ->when($promotion->starts_at, fn (Builder $query): Builder => $query->where('created_at', '>=', $promotion->starts_at))
            ->when($promotion->ends_at, fn (Builder $query): Builder => $query->where('created_at', '<=', $promotion->ends_at))
            ->count();
    }

    private function applyPromotionDiscount(float $price, string $discountType, float $discountValue): float
    {
        $calculated = $discountType === 'percentage'
            ? $price - (($price * $discountValue) / 100)
            : $price - $discountValue;

        return max($calculated, 0);
    }
}
