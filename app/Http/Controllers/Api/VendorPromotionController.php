<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class VendorPromotionController extends Controller
{
    public function products(Request $request, Promotion $promotion): JsonResponse
    {
        $isPromotionActive = Promotion::query()
            ->currentlyActive(now())
            ->whereKey($promotion->id)
            ->exists();

        if (! $isPromotionActive) {
            return response()->json(['message' => 'الحملة غير فعالة حالياً.'], 422);
        }

        $items = PromotionItem::query()
            ->approved()
            ->where('promotion_id', $promotion->id)
            ->get(['promotable_type', 'promotable_id']);

        $storeIds = $items
            ->where('promotable_type', Store::class)
            ->pluck('promotable_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $categoryIds = $items
            ->where('promotable_type', Category::class)
            ->pluck('promotable_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $subcategoryIds = $items
            ->where('promotable_type', Subcategory::class)
            ->pluck('promotable_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $productIds = $items
            ->where('promotable_type', Product::class)
            ->pluck('promotable_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $hasNoTargetingItems = empty($storeIds) && empty($categoryIds) && empty($subcategoryIds) && empty($productIds);
        if ($hasNoTargetingItems) {
            return response()->json([
                'message' => 'لا توجد عناصر معتمدة مرتبطة بهذه الحملة.',
                'promotion_id' => $promotion->id,
                'products_count' => 0,
                'products' => [],
            ]);
        }

        $productsQuery = Product::query()
            ->select(['id', 'store_id', 'subcategory_id', 'name_ar', 'name_en', 'base_price']);

        $productsQuery->where(function (Builder $query) use ($storeIds, $categoryIds, $subcategoryIds, $productIds): void {
            if (! empty($storeIds)) {
                $query->orWhereIn('store_id', $storeIds);
            }

            if (! empty($productIds)) {
                $query->orWhereIn('id', $productIds);
            }

            if (! empty($subcategoryIds)) {
                $query->orWhereIn('subcategory_id', $subcategoryIds);
            }

            if (! empty($categoryIds)) {
                $query->orWhereHas('subcategory', function (Builder $subcategoryQuery) use ($categoryIds): void {
                    $subcategoryQuery->whereIn('category_id', $categoryIds);
                });
            }
        });

        $products = $productsQuery->get()->map(function (Product $product) use ($promotion): array {
            $oldPrice = (float) $product->base_price;
            $newPrice = $this->applyPromotionDiscount($oldPrice, (string) $promotion->discount_type, (float) $promotion->discount_value);

            return [
                'id' => $product->id,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'old_price' => number_format($oldPrice, 2, '.', ''),
                'new_price' => number_format($newPrice, 2, '.', ''),
            ];
        })->values();

        return response()->json([
            'message' => 'تم جلب منتجات الحملة بنجاح.',
            'promotion_id' => $promotion->id,
            'products_count' => $products->count(),
            'products' => $products,
        ]);
    }

    public function availableCampaigns(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return response()->json(['message' => 'غير مصرح لك بعرض الحملات.'], 403);
        }

        $storeProductIds = Product::query()
            ->where('store_id', $store->id)
            ->select('id');

        $campaigns = Promotion::query()
            ->appLevel()
            ->currentlyActive(now())
            ->select([
                'id',
                'title',
                'image',
                'level',
                'discount_type',
                'discount_value',
                'starts_at',
                'ends_at',
                'is_active',
            ])
            ->withCount([
                'items as vendor_pending_requests_count' => function ($query) use ($storeProductIds, $store): void {
                    $query
                        ->where('status', 'pending')
                        ->where('store_id', $store->id)
                        ->where(function ($innerQuery) use ($storeProductIds): void {
                            $innerQuery
                                ->where(function ($productsQuery) use ($storeProductIds): void {
                                    $productsQuery
                                        ->where('promotable_type', Product::class)
                                        ->whereIn('promotable_id', $storeProductIds);
                                })
                                ->orWhere('promotable_type', Store::class);
                        });
                },
                'items as vendor_approved_items_count' => function ($query) use ($storeProductIds, $store): void {
                    $query
                        ->where('status', 'approved')
                        ->where('store_id', $store->id)
                        ->where(function ($innerQuery) use ($storeProductIds): void {
                            $innerQuery
                                ->where(function ($productsQuery) use ($storeProductIds): void {
                                    $productsQuery
                                        ->where('promotable_type', Product::class)
                                        ->whereIn('promotable_id', $storeProductIds);
                                })
                                ->orWhere('promotable_type', Store::class);
                        });
                },
            ])
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'message' => 'تم جلب الحملات المتاحة بنجاح.',
            'campaigns' => $campaigns,
        ]);
    }

    public function join(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return response()->json(['message' => 'غير مصرح لك بالانضمام للحملات.'], 403);
        }

        $data = $request->validate([
            'promotion_id' => 'required|integer|exists:promotions,id',
            'scope' => 'required|in:store,category,subcategory,product',
            'subcategory_id' => 'nullable|integer|exists:subcategories,id',
            'product_id' => 'nullable|integer|exists:products,id',
            'product_ids' => 'nullable|array|min:1',
            'product_ids.*' => 'integer|exists:products,id',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $promotion = Promotion::query()
            ->appLevel()
            ->currentlyActive(now())
            ->whereKey($data['promotion_id'])
            ->first();

        if (! $promotion) {
            return response()->json(['message' => 'الحملة غير متاحة أو غير فعالة حالياً.'], 422);
        }

        $scope = $data['scope'];
        $targetProducts = $this->resolveScopeProducts($scope, $data, $store);

        if ($targetProducts->isEmpty()) {
            return response()->json(['message' => 'لا توجد منتجات مطابقة ضمن هذا النطاق في متجرك.'], 422);
        }

        $activePromotionConflicts = $this->countActivePromotionConflicts(
            $targetProducts->pluck('id')->all(),
            $store->id,
            $promotion->id
        );
        if ($activePromotionConflicts > 0) {
            return response()->json([
                'message' => 'لا يمكن الانضمام لأن بعض المنتجات ضمن النطاق منضمة بالفعل لعروض نشطة.',
                'conflicts_count' => $activePromotionConflicts,
            ], 422);
        }

        $activeDiscountConflicts = Product::query()
            ->whereIn('id', $targetProducts->pluck('id')->all())
            ->whereHas('productDiscount', function ($query): void {
                $query->currentlyActive(now());
            })
            ->count();

        if ($activeDiscountConflicts > 0) {
            return response()->json([
                'message' => 'لا يمكن الانضمام لأن بعض المنتجات ضمن النطاق لديها خصومات نشطة.',
                'conflicts_count' => $activeDiscountConflicts,
            ], 422);
        }

        $storeItem = null;
        $items = [];
        if ($scope === 'store') {
            $storeItem = $this->upsertJoinRequest($promotion->id, Store::class, $store->id, $store->id);
            $items[] = $storeItem;
        }

        if ($scope === 'category') {
            $items[] = $this->upsertJoinRequest($promotion->id, Category::class, (int) $data['category_id'], $store->id);
        }

        if ($scope === 'subcategory') {
            $items[] = $this->upsertJoinRequest($promotion->id, Subcategory::class, (int) $data['subcategory_id'], $store->id);
        }

        if ($scope === 'product') {
            $productIds = collect($data['product_ids'] ?? [])->when(
                isset($data['product_id']),
                fn (Collection $collection) => $collection->push((int) $data['product_id'])
            )->unique()->values()->all();

            foreach ($productIds as $productId) {
                $items[] = $this->upsertJoinRequest($promotion->id, Product::class, (int) $productId, $store->id);
            }
        }

        $this->notifyAdminsAboutPromotionJoinRequest($promotion, $store, $scope, $targetProducts->count());

        return response()->json([
            'message' => 'تم إرسال طلب الانضمام بنجاح وهو بانتظار موافقة الإدارة.',
            'store_item' => $storeItem,
            'promotion_items' => $items,
            'scope' => $scope,
            'target_products_count' => $targetProducts->count(),
        ], 201);
    }

    private function notifyAdminsAboutPromotionJoinRequest(Promotion $promotion, Store $store, string $scope, int $productsCount): void
    {
        $admins = User::query()->where('role', 'admin')->get();
        $targetUrl = PromotionResource::getUrl('view', ['record' => $promotion]);

        foreach ($admins as $admin) {
            $notification = Notification::make()
                ->title('طلب انضمام جديد إلى عرض')
                ->body("متجر {$store->name} أرسل طلب انضمام ({$scope}) لعرض {$promotion->title} بعدد منتجات {$productsCount}.")
                ->info()
                ->actions([
                    Action::make('openPromotion')
                        ->label('فتح العرض')
                        ->url($targetUrl),
                ]);

            $payload = $notification->toArray();
            unset($payload['id']);
            $payload['format'] = 'filament';
            $payload['duration'] = 'persistent';
            $payload['notification_category'] = 'promotion';
            $payload['target_url'] = $targetUrl;
            $payload['promotion_id'] = $promotion->id;
            $payload['store_id'] = $store->id;

            DB::table('filament_notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\PromotionJoinRequested',
                'notifiable_type' => get_class($admin),
                'notifiable_id' => $admin->getKey(),
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function upsertJoinRequest(int $promotionId, string $promotableType, int $promotableId, int $storeId): PromotionItem
    {
        $item = PromotionItem::query()
            ->where('promotion_id', $promotionId)
            ->where('store_id', $storeId)
            ->where('promotable_type', $promotableType)
            ->where('promotable_id', $promotableId)
            ->first();

        if (! $item) {
            return PromotionItem::query()->create([
                'promotion_id' => $promotionId,
                'store_id' => $storeId,
                'promotable_type' => $promotableType,
                'promotable_id' => $promotableId,
                'status' => 'pending',
            ]);
        }

        if ($item->status !== 'approved') {
            $item->status = 'pending';
            $item->save();
        }

        return $item;
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

    private function countActivePromotionConflicts(array $productIds, int $storeId, ?int $excludePromotionId = null): int
    {
        if (empty($productIds)) {
            return 0;
        }

        $productMeta = Product::query()
            ->with('subcategory:id,category_id')
            ->whereIn('id', $productIds)
            ->get(['id', 'store_id', 'subcategory_id']);

        $subcategoryIds = $productMeta->pluck('subcategory_id')->filter()->unique()->values()->all();
        $categoryIds = $productMeta->pluck('subcategory.category_id')->filter()->unique()->values()->all();

        return Promotion::query()
            ->currentlyActive(now())
            ->when($excludePromotionId, fn ($query) => $query->whereKeyNot($excludePromotionId))
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
            ->count();
    }

    private function applyPromotionDiscount(float $price, string $discountType, float $discountValue): float
    {
        $calculated = $discountType === 'percentage'
            ? $price - (($price * $discountValue) / 100)
            : $price - $discountValue;

        return max($calculated, 0);
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
