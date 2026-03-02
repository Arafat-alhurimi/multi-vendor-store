<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Store;
use App\Models\Subcategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class PriceService
{
    public function resolveFinalPrice(Product $product, ?CarbonInterface $at = null): string
    {
        return $this->resolveDiscountedPrice($product, (float) $product->base_price, $at);
    }

    public function resolveFinalPriceForVariant(Product $product, ProductVariant $variant, ?CarbonInterface $at = null): string
    {
        $basePrice = $variant->price !== null
            ? (float) $variant->price
            : (float) $product->base_price;

        return $this->resolveDiscountedPrice($product, $basePrice, $at);
    }

    private function resolveDiscountedPrice(Product $product, float $basePrice, ?CarbonInterface $at = null): string
    {
        $dateTime = $at ?? now();

        $product->loadMissing([
            'store',
            'subcategory.category',
            'productDiscount',
        ]);

        $appLevelPromotion = $this->findActivePromotionForProduct($product, 'app', $dateTime);
        if ($appLevelPromotion) {
            return $this->formatPrice(
                $this->applyDiscount($basePrice, (string) $appLevelPromotion->discount_type, (float) $appLevelPromotion->discount_value)
            );
        }

        $storeLevelPromotion = $this->findActivePromotionForProduct($product, 'store', $dateTime);
        if ($storeLevelPromotion) {
            return $this->formatPrice(
                $this->applyDiscount($basePrice, (string) $storeLevelPromotion->discount_type, (float) $storeLevelPromotion->discount_value)
            );
        }

        $directDiscount = $product->productDiscount;
        if ($directDiscount && $this->isDirectDiscountActive($directDiscount, $dateTime)) {
            return $this->formatPrice(
                $this->applyDiscount($basePrice, (string) $directDiscount->type, (float) $directDiscount->value)
            );
        }

        return $this->formatPrice($basePrice);
    }

    private function findActivePromotionForProduct(Product $product, string $level, CarbonInterface $dateTime): ?Promotion
    {
        $categoryId = $product->subcategory?->category_id;
        $subcategoryId = $product->subcategory_id;
        $storeId = $product->store_id;

        $query = Promotion::query()
            ->currentlyActive($dateTime)
            ->where('level', $level)
            ->when($level === 'store', function (Builder $query) use ($product): void {
                $query->where('store_id', $product->store_id);
            });

        if ($level === 'app') {
            $query->where(function (Builder $promotionQuery) use ($product, $categoryId, $subcategoryId, $storeId): void {
                $promotionQuery
                    ->whereHas('items', function (Builder $itemsQuery) use ($product, $storeId): void {
                        $itemsQuery
                            ->approved()
                            ->where('promotable_type', Product::class)
                            ->where('promotable_id', $product->id)
                            ->where(function (Builder $storeContextQuery) use ($storeId): void {
                                $storeContextQuery->whereNull('store_id')->orWhere('store_id', $storeId);
                            });
                    })
                    ->orWhereHas('items', function (Builder $itemsQuery) use ($product): void {
                        $itemsQuery
                            ->approved()
                            ->where('promotable_type', Store::class)
                            ->where('promotable_id', $product->store_id);
                    })
                    ->orWhere(function (Builder $categoryScopedQuery) use ($product, $categoryId): void {
                        if (! $categoryId) {
                            $categoryScopedQuery->whereRaw('1 = 0');

                            return;
                        }

                        $categoryScopedQuery
                            ->whereHas('items', function (Builder $itemsQuery) use ($categoryId, $product): void {
                                $itemsQuery
                                    ->approved()
                                    ->where('promotable_type', Category::class)
                                    ->where('promotable_id', $categoryId)
                                    ->where(function (Builder $storeContextQuery) use ($product): void {
                                        $storeContextQuery->whereNull('store_id')->orWhere('store_id', $product->store_id);
                                    });
                            });
                    })
                    ->orWhere(function (Builder $subcategoryScopedQuery) use ($product, $subcategoryId): void {
                        if (! $subcategoryId) {
                            $subcategoryScopedQuery->whereRaw('1 = 0');

                            return;
                        }

                        $subcategoryScopedQuery
                            ->whereHas('items', function (Builder $itemsQuery) use ($subcategoryId, $product): void {
                                $itemsQuery
                                    ->approved()
                                    ->where('promotable_type', Subcategory::class)
                                    ->where('promotable_id', $subcategoryId)
                                    ->where(function (Builder $storeContextQuery) use ($product): void {
                                        $storeContextQuery->whereNull('store_id')->orWhere('store_id', $product->store_id);
                                    });
                            });
                    });
            });
        } else {
            $query->where(function (Builder $promotionQuery) use ($product, $categoryId, $subcategoryId): void {
                $promotionQuery
                    ->whereHas('items', function (Builder $itemsQuery) use ($product): void {
                        $itemsQuery
                            ->approved()
                            ->where('promotable_type', Product::class)
                            ->where('promotable_id', $product->id);
                    })
                    ->orWhereHas('items', function (Builder $itemsQuery) use ($product): void {
                        $itemsQuery
                            ->approved()
                            ->where('promotable_type', Store::class)
                            ->where('promotable_id', $product->store_id);
                    })
                    ->orWhere(function (Builder $categoryScopedQuery) use ($categoryId): void {
                        if (! $categoryId) {
                            $categoryScopedQuery->whereRaw('1 = 0');

                            return;
                        }

                        $categoryScopedQuery->whereHas('items', function (Builder $itemsQuery) use ($categoryId): void {
                            $itemsQuery
                                ->approved()
                                ->where('promotable_type', Category::class)
                                ->where('promotable_id', $categoryId);
                        });
                    })
                    ->orWhere(function (Builder $subcategoryScopedQuery) use ($subcategoryId): void {
                        if (! $subcategoryId) {
                            $subcategoryScopedQuery->whereRaw('1 = 0');

                            return;
                        }

                        $subcategoryScopedQuery->whereHas('items', function (Builder $itemsQuery) use ($subcategoryId): void {
                            $itemsQuery
                                ->approved()
                                ->where('promotable_type', Subcategory::class)
                                ->where('promotable_id', $subcategoryId);
                        });
                    });
            });
        }

        return $query
            ->orderBy('starts_at')
            ->first();
    }

    private function isDirectDiscountActive(ProductDiscount $discount, CarbonInterface $dateTime): bool
    {
        if (! $discount->is_active) {
            return false;
        }

        $startsAt = $discount->starts_at;
        $endsAt = $discount->ends_at;

        if ($startsAt && $startsAt->gt($dateTime)) {
            return false;
        }

        if ($endsAt && $endsAt->lt($dateTime)) {
            return false;
        }

        return true;
    }

    private function applyDiscount(float $price, string $discountType, float $discountValue): float
    {
        $calculatedPrice = $discountType === 'percentage'
            ? $price - (($price * $discountValue) / 100)
            : $price - $discountValue;

        return max($calculatedPrice, 0);
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }
}
