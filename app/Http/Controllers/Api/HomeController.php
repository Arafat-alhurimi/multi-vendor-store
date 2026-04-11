<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use App\Models\Subcategory;
use App\Services\PriceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request, PriceService $priceService): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => 'nullable|integer|exists:categories,id',
            'products_per_page' => 'nullable|integer|min:1|max:50',
            'lang' => 'nullable|string|in:ar,en',
        ]);

        $categoryId = isset($validated['category_id']) ? (int) $validated['category_id'] : null;
        $productsPerPage = (int) ($validated['products_per_page'] ?? 10);
        $locale = $this->resolveLocale($request, $validated['lang'] ?? null);

        $categories = $this->resolveCategories($categoryId, $locale);

        $ads = Ad::query()
            ->select([
                'id',
                'vendor_id',
                'media_type',
                'media_path',
                'click_action',
                'action_id',
                'starts_at',
                'ends_at',
            ])
            ->latest('id')
            ->get();

        $promotions = $this->promotionsQuery($categoryId)
            ->get()
            ->map(fn (Promotion $promotion): array => [
                'id' => $promotion->id,
                'title' => $promotion->title,
                'image' => $promotion->image,
                'level' => $promotion->level,
                'discount_type' => $promotion->discount_type,
                'discount_value' => $promotion->discount_value,
                'starts_at' => $promotion->starts_at,
                'ends_at' => $promotion->ends_at,
                'is_active' => (bool) $promotion->effective_is_active,
            ])
            ->values();

        $products = $this->productsQuery($categoryId)
            ->paginate($productsPerPage, ['*'], 'products_page')
            ->through(fn (Product $product): array => $this->mapProduct($product, $priceService, $locale));

        $stores = $this->storesQuery($categoryId)
            ->get()
            ->map(fn (Store $store): array => [
                'id' => $store->id,
                'name' => $store->name,
                'description' => $store->description,
                'city' => $store->city,
                'address' => $store->address,
                'logo' => $store->logo_url ?? $store->logo,
                'is_active' => (bool) $store->is_active,
                'products_count' => (int) $store->products_count,
                'categories' => $store->categories
                    ->map(fn (Category $category): array => [
                        'id' => $category->id,
                        'name' => $this->localize($category->name_ar, $category->name_en, $locale),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values();

        return response()->json([
            'status' => true,
            'message' => $this->successMessage($locale),
            'filters' => [
                'category_id' => $categoryId,
                'language' => $locale,
            ],
            'categories_type' => $categoryId ? 'subcategories' : 'categories',
            'ads' => $ads,
            'categories' => $categories,
            'promotions' => $promotions,
            'products' => $products,
            'stores' => $stores,
        ]);
    }

    private function resolveCategories(?int $categoryId, string $locale)
    {
        $items = $categoryId
            ? Subcategory::query()
                ->where('category_id', $categoryId)
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('id')
                ->get(['id', 'category_id', 'name_ar', 'name_en', 'description_ar', 'description_en', 'image', 'is_active', 'order'])
            : Category::query()
                ->where('is_active', true)
                ->orderBy('order')
                ->orderBy('id')
                ->get(['id', 'name_ar', 'name_en', 'description_ar', 'description_en', 'image', 'is_active', 'order']);

        return $items
            ->map(function ($item) use ($locale): array {
                return [
                    'id' => $item->id,
                    'category_id' => $item->category_id ?? null,
                    'name' => $this->localize($item->name_ar, $item->name_en, $locale),
                    'description' => $this->localize($item->description_ar, $item->description_en, $locale),
                    'image' => $item->image_url ?? $item->image,
                    'is_active' => (bool) $item->is_active,
                    'order' => (int) $item->order,
                ];
            })
            ->values();
    }

    private function promotionsQuery(?int $categoryId): Builder
    {
        return Promotion::query()
            ->currentlyActive(now())
            ->when($categoryId, function (Builder $query) use ($categoryId): void {
                $query->where(function (Builder $promotionQuery) use ($categoryId): void {
                    $promotionQuery
                        ->whereHas('items', function (Builder $itemsQuery) use ($categoryId): void {
                            $itemsQuery
                                ->approved()
                                ->where('promotable_type', Category::class)
                                ->where('promotable_id', $categoryId);
                        })
                        ->orWhereHas('items', function (Builder $itemsQuery) use ($categoryId): void {
                            $itemsQuery
                                ->approved()
                                ->where('promotable_type', Subcategory::class)
                                ->whereIn('promotable_id', Subcategory::query()->where('category_id', $categoryId)->select('id'));
                        })
                        ->orWhereHas('items', function (Builder $itemsQuery) use ($categoryId): void {
                            $itemsQuery
                                ->approved()
                                ->where('promotable_type', Product::class)
                                ->whereIn('promotable_id', Product::query()
                                    ->whereHas('subcategory', function (Builder $subcategoryQuery) use ($categoryId): void {
                                        $subcategoryQuery->where('category_id', $categoryId);
                                    })
                                    ->select('id'));
                        })
                        ->orWhereHas('items', function (Builder $itemsQuery) use ($categoryId): void {
                            $itemsQuery
                                ->approved()
                                ->where('promotable_type', Store::class)
                                ->whereIn('promotable_id', Store::query()
                                    ->where(function (Builder $storeQuery) use ($categoryId): void {
                                        $storeQuery
                                            ->whereHas('categories', fn (Builder $categoriesQuery): Builder => $categoriesQuery->whereKey($categoryId))
                                            ->orWhereHas('products.subcategory', function (Builder $subcategoryQuery) use ($categoryId): void {
                                                $subcategoryQuery->where('category_id', $categoryId);
                                            });
                                    })
                                    ->select('id'));
                        });
                });
            })
            ->orderBy('starts_at')
            ->orderByDesc('id');
    }

    private function productsQuery(?int $categoryId): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->whereHas('store', fn (Builder $storeQuery): Builder => $storeQuery->where('is_active', true))
            ->when($categoryId, function (Builder $query) use ($categoryId): void {
                $query->whereHas('subcategory', function (Builder $subcategoryQuery) use ($categoryId): void {
                    $subcategoryQuery->where('category_id', $categoryId);
                });
            })
            ->with([
                'store:id,name,logo,is_active',
                'subcategory:id,category_id,name_ar,name_en',
                'media:id,mediable_id,mediable_type,url,file_type',
                'productDiscount',
            ])
            ->withAvg('ratings', 'value')
            ->withCount('ratings')
            ->latest('id');
    }

    private function storesQuery(?int $categoryId): Builder
    {
        return Store::query()
            ->where('is_active', true)
            ->when($categoryId, function (Builder $query) use ($categoryId): void {
                $query->where(function (Builder $storeQuery) use ($categoryId): void {
                    $storeQuery
                        ->whereHas('categories', fn (Builder $categoriesQuery): Builder => $categoriesQuery->whereKey($categoryId))
                        ->orWhereHas('products.subcategory', function (Builder $subcategoryQuery) use ($categoryId): void {
                            $subcategoryQuery->where('category_id', $categoryId);
                        });
                });
            })
            ->with(['categories:id,name_ar,name_en'])
            ->withCount([
                'products' => function (Builder $productsQuery): void {
                    $productsQuery->where('is_active', true);
                },
            ])
            ->latest('id');
    }

    private function mapProduct(Product $product, PriceService $priceService, string $locale): array
    {
        $basePrice = (float) $product->base_price;
        $finalPrice = (float) $priceService->resolveFinalPrice($product);

        return [
            'id' => $product->id,
            'name' => $this->localize($product->name_ar, $product->name_en, $locale),
            'description' => $this->localize($product->description_ar, $product->description_en, $locale),
            'image' => $product->media->first()?->url,
            'stock' => (int) $product->stock,
            'is_active' => (bool) $product->is_active,
            'base_price' => number_format($basePrice, 2, '.', ''),
            'final_price' => number_format($finalPrice, 2, '.', ''),
            'has_offer' => $finalPrice < $basePrice,
            'store' => $product->store ? [
                'id' => $product->store->id,
                'name' => $product->store->name,
                'logo' => $product->store->logo,
            ] : null,
            'subcategory' => $product->subcategory ? [
                'id' => $product->subcategory->id,
                'category_id' => $product->subcategory->category_id,
                'name' => $this->localize($product->subcategory->name_ar, $product->subcategory->name_en, $locale),
            ] : null,
            'rating' => [
                'average' => round((float) ($product->ratings_avg_value ?? 0), 2),
                'count' => (int) ($product->ratings_count ?? 0),
            ],
        ];
    }

    private function resolveLocale(Request $request, ?string $requestedLocale = null): string
    {
        if ($requestedLocale === 'en') {
            return 'en';
        }

        return 'ar';
    }

    private function localize(?string $arabicValue, ?string $englishValue, string $locale): ?string
    {
        return $locale === 'en'
            ? ($englishValue ?: $arabicValue)
            : ($arabicValue ?: $englishValue);
    }

    private function successMessage(string $locale): string
    {
        return $locale === 'en'
            ? 'Home data fetched successfully.'
            : 'تم جلب بيانات الصفحة الرئيسية بنجاح.';
    }
}
