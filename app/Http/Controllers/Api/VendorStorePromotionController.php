<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class VendorStorePromotionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return response()->json(['message' => 'غير مصرح لك بإدارة عروض المتجر.'], 403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:4096',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after_or_equal:starts_at',
            'scope' => 'required|in:store,category,subcategory,product',
            'category_id' => 'nullable|integer|exists:categories,id',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'product_ids' => 'nullable|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
        ]);

        $scope = $data['scope'];
        $targetProducts = $this->resolveScopeProducts($scope, $data, $store);

        if ($targetProducts->isEmpty()) {
            return response()->json(['message' => 'لا توجد منتجات مطابقة ضمن هذا النطاق في متجرك.'], 422);
        }

        if ($this->hasActivePromotionConflict($targetProducts->pluck('id')->all(), $store->id)) {
            return response()->json(['message' => 'لا يمكن إنشاء العرض لأن بعض المنتجات منضمة لعروض نشطة بالفعل.'], 422);
        }

        $hasActiveDiscountConflict = Product::query()
            ->whereIn('id', $targetProducts->pluck('id')->all())
            ->whereHas('productDiscount', function ($query): void {
                $query->currentlyActive(now());
            })
            ->exists();

        if ($hasActiveDiscountConflict) {
            return response()->json(['message' => 'لا يمكن إنشاء العرض لأن بعض المنتجات عليها خصومات نشطة.'], 422);
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('promotions', 's3');
        }

        $promotion = DB::transaction(function () use ($data, $scope, $store): Promotion {
            $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;
            $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;
            $now = now();

            $promotion = Promotion::query()->create([
                'title' => $data['title'],
                'image' => $data['image'] ?? null,
                'level' => 'store',
                'store_id' => $store->id,
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'starts_at' => $data['starts_at'],
                'ends_at' => $data['ends_at'],
                'is_active' => (! $startsAt || $startsAt->lte($now))
                    && (! $endsAt || $endsAt->gte($now)),
            ]);

            if ($scope === 'store') {
                PromotionItem::query()->create([
                    'promotion_id' => $promotion->id,
                    'store_id' => $store->id,
                    'promotable_type' => Store::class,
                    'promotable_id' => $store->id,
                    'status' => 'approved',
                ]);

                return $promotion;
            }

            if ($scope === 'category') {
                PromotionItem::query()->create([
                    'promotion_id' => $promotion->id,
                    'store_id' => $store->id,
                    'promotable_type' => Category::class,
                    'promotable_id' => (int) $data['category_id'],
                    'status' => 'approved',
                ]);

                return $promotion;
            }

            if ($scope === 'subcategory') {
                PromotionItem::query()->create([
                    'promotion_id' => $promotion->id,
                    'store_id' => $store->id,
                    'promotable_type' => Subcategory::class,
                    'promotable_id' => (int) $data['subcategory_id'],
                    'status' => 'approved',
                ]);

                return $promotion;
            }

            $productIds = collect($data['product_ids'] ?? [])->when(
                isset($data['product_id']),
                fn (Collection $collection) => $collection->push((int) $data['product_id'])
            )->unique()->values()->all();

            foreach ($productIds as $productId) {
                PromotionItem::query()->create([
                    'promotion_id' => $promotion->id,
                    'store_id' => $store->id,
                    'promotable_type' => Product::class,
                    'promotable_id' => (int) $productId,
                    'status' => 'approved',
                ]);
            }

            return $promotion;
        });

        return response()->json([
            'message' => 'تم إنشاء عرض مستوى المتجر بنجاح.',
            'promotion' => $promotion->load('items'),
            'scope' => $scope,
            'target_products_count' => $targetProducts->count(),
        ], 201);
    }

    public function deactivate(Request $request, Promotion $promotion): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return response()->json(['message' => 'غير مصرح لك بإدارة عروض المتجر.'], 403);
        }

        if ($promotion->level !== 'store' || (int) $promotion->store_id !== (int) $store->id) {
            return response()->json(['message' => 'لا يمكنك إلغاء تفعيل هذا العرض.'], 403);
        }

        $promotion->update([
            'is_active' => false,
            'ends_at' => now(),
        ]);

        return response()->json([
            'message' => 'تم إلغاء تفعيل العرض بنجاح.',
            'promotion' => $promotion,
        ]);
    }

    private function resolveScopeProducts(string $scope, array $data, Store $store): Collection
    {
        if ($scope === 'store') {
            return Product::query()->where('store_id', $store->id)->get(['id', 'store_id', 'subcategory_id']);
        }

        if ($scope === 'category') {
            if (! isset($data['category_id']) || ! $store->categories()->whereKey((int) $data['category_id'])->exists()) {
                return collect();
            }

            return Product::query()
                ->where('store_id', $store->id)
                ->whereHas('subcategory', function ($query) use ($data): void {
                    $query->where('category_id', (int) $data['category_id']);
                })
                ->get(['id', 'store_id', 'subcategory_id']);
        }

        if ($scope === 'subcategory') {
            if (! isset($data['subcategory_id'])) {
                return collect();
            }

            $subcategory = Subcategory::query()->find((int) $data['subcategory_id']);
            if (! $subcategory || ! $store->categories()->whereKey($subcategory->category_id)->exists()) {
                return collect();
            }

            return Product::query()
                ->where('store_id', $store->id)
                ->where('subcategory_id', (int) $data['subcategory_id'])
                ->get(['id', 'store_id', 'subcategory_id']);
        }

        $productIds = collect($data['product_ids'] ?? [])->when(
            isset($data['product_id']),
            fn (Collection $collection) => $collection->push((int) $data['product_id'])
        )->unique()->values()->all();

        if (empty($productIds)) {
            return collect();
        }

        $products = Product::query()
            ->where('store_id', $store->id)
            ->whereIn('id', $productIds)
            ->get(['id', 'store_id', 'subcategory_id']);

        if ($products->count() !== count($productIds)) {
            return collect();
        }

        return $products;
    }

    private function hasActivePromotionConflict(array $productIds, int $storeId): bool
    {
        if (empty($productIds)) {
            return false;
        }

        $productMeta = Product::query()
            ->with('subcategory:id,category_id')
            ->whereIn('id', $productIds)
            ->get(['id', 'store_id', 'subcategory_id']);

        $subcategoryIds = $productMeta->pluck('subcategory_id')->filter()->unique()->values()->all();
        $categoryIds = $productMeta->pluck('subcategory.category_id')->filter()->unique()->values()->all();

        return Promotion::query()
            ->currentlyActive(now())
            ->whereHas('items', function ($query) use ($productIds, $subcategoryIds, $categoryIds, $storeId): void {
                $query
                    ->approved()
                    ->where(function ($matchQuery) use ($productIds, $subcategoryIds, $categoryIds, $storeId): void {
                        $matchQuery
                            ->where(function ($directProduct) use ($productIds, $storeId): void {
                                $directProduct
                                    ->where('promotable_type', Product::class)
                                    ->whereIn('promotable_id', $productIds)
                                    ->where(function ($storeContext) use ($storeId): void {
                                        $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                                    });
                            })
                            ->orWhere(function ($storeScope) use ($storeId): void {
                                $storeScope
                                    ->where('promotable_type', Store::class)
                                    ->where('promotable_id', $storeId);
                            });

                        if (! empty($subcategoryIds)) {
                            $matchQuery->orWhere(function ($subcategoryScope) use ($subcategoryIds, $storeId): void {
                                $subcategoryScope
                                    ->where('promotable_type', Subcategory::class)
                                    ->whereIn('promotable_id', $subcategoryIds)
                                    ->where(function ($storeContext) use ($storeId): void {
                                        $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                                    });
                            });
                        }

                        if (! empty($categoryIds)) {
                            $matchQuery->orWhere(function ($categoryScope) use ($categoryIds, $storeId): void {
                                $categoryScope
                                    ->where('promotable_type', Category::class)
                                    ->whereIn('promotable_id', $categoryIds)
                                    ->where(function ($storeContext) use ($storeId): void {
                                        $storeContext->whereNull('store_id')->orWhere('store_id', $storeId);
                                    });
                            });
                        }
                    });
            })
            ->exists();
    }

    private function resolveVendorStore(Request $request): ?Store
    {
        $user = $request->user();

        if (! $user || $user->role !== 'vendor') {
            return null;
        }

        return $user->stores()->first();
    }
}
